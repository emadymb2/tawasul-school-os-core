<?php
namespace Tos\Module\TawasulCore\Controller;

use Tos\Module\TawasulCore\Http\ApiException;
use Tos\Module\TawasulCore\Http\Request;
use Tos\Module\TawasulCore\Resource\Registry;
use Tos\Module\TawasulCore\Support\QueryBuilder;

/**
 * GET /v2/{resource}/aggregate
 *
 * Summary statistics for any resource — counts, sums, averages — optionally
 * grouped by a column. This is the endpoint a dashboard or report screen hits
 * instead of fetching hundreds of rows and crunching them client-side.
 *
 *   ?group_by=yearGroupID        Group results by this column (alias or name).
 *   ?metrics=count               Just the group counts.
 *   ?metrics=count,total:gross,score:avg
 *                                Count + sum of total + average of score.
 *
 * The group_by and metric fields are resolved against the resource definition
 * (same alias resolution as ?filter= and ?sort=), so callers never send raw
 * SQL column names. Unrecognised fields are rejected with a 400 and the list
 * of valid ones is returned in the error details.
 *
 *   GET /v2/students/aggregate?group_by=tawasulYearGroupID&metrics=count
 *   → [{"tawasulYearGroupID": "KG", "count": 24}, {"tawasulYearGroupID": "1A", "count": 26}]
 */
class AggregateController
{
    protected $crud;

    public function __construct(CrudController $crud)
    {
        $this->crud = $crud;
    }

    public function aggregate(string $name, Request $request): array
    {
        $resource = Registry::get($name);
        $this->crud->guard($resource, 'GET');

        $groupBy = $this->resolveFields(
            $resource,
            (string) $request->query('group_by', '')
        );

        $metrics = $this->parseMetrics(
            $resource,
            (string) $request->query('metrics', 'count')
        );

        $schoolYearID = $this->crud->resolveSchoolYearPublic($request);

        $builder = (new QueryBuilder($resource))->withCurrentPerson($this->crud->getCredentialPersonID())->applyRequest(
            $request,
            1,
            1,
            $schoolYearID
        );

        $selectParts = [];
        $groupByAliases = [];
        foreach ($groupBy as $alias => $column) {
            // The outer query references columns by their bare name (as exposed
            // by the subquery), not by table-qualified names.
            $selectParts[] = $alias.' AS '.$alias;
            $groupByAliases[] = $alias;
        }

        foreach ($metrics as $label => $expr) {
            $selectParts[] = $expr.' AS '.$label;
        }

        $groupSQL = implode(', ', $selectParts);
        $groupBySQL = empty($groupByAliases) ? '' : ' GROUP BY '.implode(', ', $groupByAliases);

        $pdo = $this->crud->getPdo();

        $countSQL = 'SELECT COUNT(*) FROM ('.$resource['select'].$builder->getWhereSQL().') AS total';
        $countStmt = $pdo->prepare($countSQL);
        $countStmt->execute($builder->getBindings());
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT '.$groupSQL
            .' FROM ('.$resource['select'].$builder->getWhereSQL().') AS data'
            .$groupBySQL
            .' ORDER BY '.(empty($groupByAliases) ? 'NULL' : implode(', ', $groupByAliases));

        $stmt = $pdo->prepare($sql);
        $stmt->execute($builder->getBindings());

        return [
            'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'meta' => [
                'resource' => $resource['name'],
                'group_by' => array_keys($groupBy),
                'metrics' => array_keys($metrics),
                'total' => $total,
            ],
        ];
    }

    /**
     * Resolves comma-separated aliases against the resource's filters and sort
     * maps. Accepts both aliases ("yearGroup") and qualified column names
     * ("tawasulStudentEnrolment.tawasulYearGroupID").
     */
    protected function resolveFields(array $resource, string $raw): array
    {
        $fields = array_filter(array_map('trim', explode(',', $raw)), 'strlen');
        $resolved = [];

        foreach ($fields as $field) {
            $column = $this->resolveColumn($resource, $field);
            if ($column === null) {
                throw ApiException::badRequest(
                    'Cannot group or aggregate by "'.$field.'".',
                    [
                        'availableFilters' => array_keys($resource['filters'] ?? []),
                        'availableSorts' => array_keys($resource['sort'] ?? []),
                    ]
                );
            }
            $resolved[$field] = ['raw' => $column, 'alias' => $field];
        }

        return $resolved;
    }

    /**
     * Parses the metrics parameter: "count", "field:sum", "field:avg", etc.
     */
    protected function parseMetrics(array $resource, string $raw): array
    {
        $metrics = array_filter(array_map('trim', explode(',', $raw)), 'strlen');
        $parsed = [];

        foreach ($metrics as $metric) {
            $metric = strtolower($metric);

            if ($metric === 'count') {
                $parsed['count'] = 'COUNT(*)';
                continue;
            }

            $parts = explode(':', $metric, 2);
            $field = $parts[0];
            $func = count($parts) > 1 ? strtoupper($parts[1]) : 'SUM';

            if (!in_array($func, ['SUM', 'AVG', 'MIN', 'MAX', 'COUNT'], true)) {
                throw ApiException::badRequest(
                    'Metric function "'.$func.'" is not supported. Use count, sum, avg, min or max.',
                    ['example' => 'field:sum']
                );
            }

            $column = $this->resolveColumn($resource, $field);
            if ($column === null) {
                throw ApiException::badRequest(
                    'Cannot aggregate by "'.$field.'".',
                    [
                        'availableFilters' => array_keys($resource['filters'] ?? []),
                        'availableSorts' => array_keys($resource['sort'] ?? []),
                    ]
                );
            }

            $alias = $func === 'COUNT' ? 'count_'.$field : $func.strtolower('_'.$field);
            $parsed[$alias] = $func.'('.$column.')';
        }

        return $parsed;
    }

    /**
     * Resolves a field alias to a qualified column name using the resource's
     * filters and sort maps. If the field already looks like a qualified name
     * (contains a dot), it is returned as-is.
     */
    protected function resolveColumn(array $resource, string $field): ?string
    {
        // Already a qualified column or expression?
        if (strpos($field, '.') !== false || preg_match('/^[\w\.\*]+$/', $field)) {
            // Check filters map.
            if (isset($resource['filters'][$field])) {
                return $resource['filters'][$field];
            }
            // Check sort map.
            if (isset($resource['sort'][$field])) {
                return $resource['sort'][$field];
            }
            // If it contains a dot, accept it as a qualified column name.
            if (strpos($field, '.') !== false) {
                return $field;
            }
        }

        // Try filter alias.
        if (isset($resource['filters'][$field])) {
            return $resource['filters'][$field];
        }

        // Try sort alias.
        if (isset($resource['sort'][$field])) {
            return $resource['sort'][$field];
        }

        return null;
    }
}
