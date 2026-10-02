<?php
namespace Tos\Module\TawasulCore\Resource;

use PDO;
use Tos\Module\TawasulCore\Auth\Credential;
use Tos\Module\TawasulCore\Auth\Permissions;
use Tos\Module\TawasulCore\Http\ApiException;

/**
 * Relation expansion: ?include=family,form-group or /students/{id}/classes.
 *
 * Every definition may declare relations. A "belongsTo" relation points at a
 * parent row through a local foreign key; a "hasMany" relation lists child
 * rows whose foreign key points back at this record. Expansion re-uses the
 * target resource definition, so the same scope, module and role checks apply
 * to an included record as to a direct request for it — an integrator can
 * never reach data through include= that a plain GET would refuse.
 */
class Relations
{
    /** Hard ceiling so ?include= can never fan out into hundreds of queries. */
    const MAX_INCLUDES = 8;
    const MAX_CHILDREN = 100;

    protected $pdo;
    protected $permissions;
    protected $credential;

    public function __construct(PDO $pdo, Permissions $permissions, Credential $credential)
    {
        $this->pdo = $pdo;
        $this->permissions = $permissions;
        $this->credential = $credential;
    }

    /**
     * Parses the ?include= parameter against a resource definition.
     *
     * @return array<string, array> relation name => relation definition
     */
    public function parse(array $resource, string $include): array
    {
        $include = trim($include);
        if ($include === '') {
            return [];
        }

        $available = $resource['relations'] ?? [];
        $requested = array_filter(array_map('trim', explode(',', $include)), 'strlen');

        if (count($requested) > self::MAX_INCLUDES) {
            throw ApiException::badRequest('At most '.self::MAX_INCLUDES.' relations can be included in one request.');
        }

        $selected = [];
        foreach ($requested as $name) {
            if (!isset($available[$name])) {
                throw ApiException::badRequest(
                    'Cannot include "'.$name.'" on '.$resource['name'].'.',
                    ['availableIncludes' => array_keys($available)]
                );
            }
            $selected[$name] = $available[$name];
        }

        return $selected;
    }

    /**
     * Attaches the requested relations to a set of rows, using one query per
     * relation rather than one per row.
     */
    public function expand(array $rows, array $relations, int $childLimit = self::MAX_CHILDREN): array
    {
        if (empty($rows) || empty($relations)) {
            return $rows;
        }

        foreach ($relations as $name => $relation) {
            $target = Registry::get($relation['resource']);
            $this->permissions->authorise($this->credential, $target, 'GET');

            $type = $relation['type'] ?? 'belongsTo';
            $localKey = $relation['localKey'];
            $foreignKey = $relation['foreignKey'];
            $foreignColumn = $target['table'].'.'.$foreignKey;

            $values = [];
            foreach ($rows as $row) {
                if (isset($row[$localKey]) && $row[$localKey] !== '' && $row[$localKey] !== null) {
                    $values[(string) $row[$localKey]] = true;
                }
            }

            if (empty($values)) {
                foreach ($rows as &$row) {
                    $row[$name] = $type === 'hasMany' ? [] : null;
                }
                unset($row);
                continue;
            }

            $values = array_keys($values);
            $placeholders = [];
            $bindings = [];
            foreach ($values as $i => $value) {
                $placeholders[] = ':r'.$i;
                $bindings['r'.$i] = $value;
            }

            $sql = $target['select'].' WHERE '.$foreignColumn.' IN ('.implode(',', $placeholders).')';
            if (!empty($target['where'])) {
                $sql .= ' AND '.implode(' AND ', $target['where']);
            }
            $sql .= ' LIMIT '.(int) ($childLimit * count($values));

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);

            $grouped = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $related) {
                foreach ($target['sensitive'] as $column) {
                    unset($related[$column]);
                }
                $key = (string) ($related[$foreignKey] ?? '');
                $grouped[$key][] = $related;
            }

            foreach ($rows as &$row) {
                $key = (string) ($row[$localKey] ?? '');
                $matches = $grouped[$key] ?? [];
                $row[$name] = $type === 'hasMany' ? array_slice($matches, 0, $childLimit) : ($matches[0] ?? null);
            }
            unset($row);
        }

        return $rows;
    }

    /**
     * Serves /{resource}/{id}/{relation} by reusing the same definitions.
     */
    public function fetchRelation(array $resource, string $id, string $name, int $limit = self::MAX_CHILDREN): array
    {
        $relations = $resource['relations'] ?? [];
        if (!isset($relations[$name])) {
            throw ApiException::notFound(
                'There is no "'.$name.'" relation on '.$resource['name'].'.',
                ['availableRelations' => array_keys($relations)]
            );
        }

        $idColumn = $resource['idColumn'] ?: $resource['table'].'.'.$resource['primaryKey'];
        $stmt = $this->pdo->prepare($resource['select'].' WHERE '.$idColumn.'=:recordID LIMIT 1');
        $stmt->execute(['recordID' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($row)) {
            throw ApiException::notFound('No '.$resource['title'].' record with id '.$id.'.');
        }

        $relation = $relations[$name];
        $expanded = $this->expand([$row], [$name => $relation], $limit);
        $value = $expanded[0][$name] ?? null;

        $meta = [
            'resource' => $resource['name'],
            'relation' => $name,
            'target' => $relation['resource'],
            'type' => $relation['type'] ?? 'belongsTo',
        ];

        // A child list tells the caller how many rows came back and whether the
        // per-request cap trimmed them, so nobody mistakes a cap for the total.
        if (is_array($value)) {
            $meta['count'] = count($value);
            $meta['limit'] = $limit;
            $meta['truncated'] = count($value) >= $limit;
        }

        return ['data' => $value, 'meta' => $meta];
    }
}
