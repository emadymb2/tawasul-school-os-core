<?php
namespace Gibbon\Module\RestAPI\Support;

use Gibbon\Module\RestAPI\Http\ApiException;
use Gibbon\Module\RestAPI\Http\Request;

/**
 * Turns a resource definition plus query-string parameters into one safe,
 * fully parameterised SQL statement.
 *
 * Only columns named in the definition can ever reach the SQL. Anything the
 * caller sends that is not in filters, search or sort is rejected with a 400
 * rather than silently ignored, so integrators find their typos immediately.
 *
 * Supported query parameters
 *   page, pageSize          pagination
 *   sort                    comma separated, "-name" for descending
 *   search                  free text across the resource search columns
 *   fields                  comma separated subset of returned columns
 *   {filter}                exact match, or a comma separated list for IN
 *   {filter}From/{filter}To inclusive range, for dates and numbers
 *   tawasulSchoolYearID      applied automatically to year-scoped resources
 */
class QueryBuilder
{
    protected $resource;
    protected $where = [];
    protected $bindings = [];
    protected $page = 1;
    protected $pageSize = 50;
    protected $orderBy = '';
    protected $fields = [];

    /** Parameters that are never treated as filters. */
    const RESERVED = ['page', 'pageSize', 'sort', 'search', 'fields', 'endpoint', 'format', 'tawasulSchoolYearID', 'path', 'batchSize', 'group_by', 'metrics'];

    public function __construct(array $resource)
    {
        $this->resource = $resource;
        $this->where = $resource['where'];
        $this->orderBy = $resource['defaultSort'] ?? $resource['primaryKey'];
    }

    public function applyRequest(Request $request, int $defaultPageSize, int $maxPageSize, ?string $schoolYearID, ?string $personID = null): self
    {
        $this->page = max(1, $request->queryInt('page', 1));
        $this->pageSize = $request->queryInt('pageSize', $defaultPageSize);
        if ($this->pageSize < 1) {
            $this->pageSize = $defaultPageSize;
        }
        if ($this->pageSize > $maxPageSize) {
            throw ApiException::badRequest('pageSize may not exceed '.$maxPageSize.'.');
        }

        if (!empty($this->resource['yearFilter']) && !empty($schoolYearID)) {
            $this->where[] = $this->resource['yearFilter'].'=:apiSchoolYearID';
            $this->bindings['apiSchoolYearID'] = $schoolYearID;
        }

        $this->applyPersonScope($personID);
        $this->applyFilters($request);
        $this->applySearch((string) $request->query('search', ''));
        $this->applySort((string) $request->query('sort', ''));
        $this->applyFields((string) $request->query('fields', ''));

        return $this;
    }

    /**
     * Restricts a resource to the caller.
     *
     * A resource that declares 'personFilter' holds rows belonging to one person
     * at a time — chat conversations, someone's own enrolments — and must never
     * be listed to anyone else. The filter is applied whenever the caller is
     * known, and a request made without one is refused outright rather than
     * being allowed through unscoped, because "no credential" must never widen a
     * result set.
     */
    protected function applyPersonScope(?string $personID): void
    {
        if (empty($this->resource['personFilter'])) {
            return;
        }

        if ($personID === null || $personID === '') {
            throw ApiException::forbidden('This collection is only available to a signed-in user.');
        }

        $this->where[] = $this->resource['personFilter'];
        $this->bindings['currentPersonID'] = $personID;
    }

    protected function applyFilters(Request $request): void
    {
        $filters = $this->resource['filters'];
        $index = 0;

        foreach ($request->getQueryAll() as $key => $value) {
            if (in_array($key, self::RESERVED, true)) {
                continue;
            }

            $base = preg_replace('/(From|To)$/', '', $key);
            $range = $base !== $key ? substr($key, strlen($base)) : '';

            if (!isset($filters[$base])) {
                throw ApiException::badRequest(
                    'Unknown filter "'.$key.'" for '.$this->resource['name'].'.',
                    ['availableFilters' => array_keys($filters)]
                );
            }

            $column = $filters[$base];
            $value = is_array($value) ? implode(',', $value) : (string) $value;
            $param = 'f'.($index++);

            if ($range === 'From') {
                $this->where[] = $column.' >= :'.$param;
                $this->bindings[$param] = $value;
            } elseif ($range === 'To') {
                $this->where[] = $column.' <= :'.$param;
                $this->bindings[$param] = $value;
            } elseif ($value === 'null') {
                $this->where[] = $column.' IS NULL';
            } elseif (strpos($value, ',') !== false) {
                $parts = [];
                foreach (array_filter(array_map('trim', explode(',', $value)), 'strlen') as $i => $item) {
                    $parts[] = ':'.$param.'i'.$i;
                    $this->bindings[$param.'i'.$i] = $item;
                }
                if (!empty($parts)) {
                    $this->where[] = $column.' IN ('.implode(',', $parts).')';
                }
            } else {
                $this->where[] = $column.'=:'.$param;
                $this->bindings[$param] = $value;
            }
        }
    }

    protected function applySearch(string $search): void
    {
        $search = trim($search);
        if ($search === '' || empty($this->resource['search'])) {
            return;
        }

        $clauses = [];
        foreach ($this->resource['search'] as $i => $column) {
            $clauses[] = $column.' LIKE :search'.$i;
            $this->bindings['search'.$i] = '%'.$search.'%';
        }
        $this->where[] = '('.implode(' OR ', $clauses).')';
    }

    protected function applySort(string $sort): void
    {
        $sort = trim($sort);
        if ($sort === '') {
            return;
        }

        $allowed = $this->resource['sort'];
        $parts = [];

        foreach (array_filter(array_map('trim', explode(',', $sort)), 'strlen') as $item) {
            $direction = 'ASC';
            if (strpos($item, '-') === 0) {
                $direction = 'DESC';
                $item = substr($item, 1);
            }
            if (!isset($allowed[$item])) {
                throw ApiException::badRequest(
                    'Cannot sort by "'.$item.'".',
                    ['availableSorts' => array_keys($allowed)]
                );
            }
            $parts[] = $allowed[$item].' '.$direction;
        }

        if (!empty($parts)) {
            $this->orderBy = implode(', ', $parts);
        }
    }

    protected function applyFields(string $fields): void
    {
        $fields = trim($fields);
        if ($fields !== '') {
            $this->fields = array_filter(array_map('trim', explode(',', $fields)), 'strlen');
        }
    }

    public function whereID(string $id): self
    {
        $column = $this->resource['idColumn'] ?: $this->resource['table'].'.'.$this->resource['primaryKey'];
        $this->where[] = $column.'=:recordID';
        $this->bindings['recordID'] = $id;

        return $this;
    }

    public function getSelectSQL(): string
    {
        return $this->resource['select']
            .$this->getWhereSQL()
            .' ORDER BY '.$this->orderBy
            .' LIMIT '.(int) $this->pageSize.' OFFSET '.(($this->page - 1) * (int) $this->pageSize);
    }

    /**
     * SELECT without LIMIT/OFFSET — used by the export endpoint to page
     * through every matching record under its own control.
     */
    public function getSelectSQLAll(): string
    {
        return $this->resource['select']
            .$this->getWhereSQL()
            .' ORDER BY '.$this->orderBy;
    }

    public function getCountSQL(): string
    {
        // Wrapping the definition keeps aggregates and sub-selects intact.
        return 'SELECT COUNT(*) FROM ('.$this->resource['select'].$this->getWhereSQL().') AS countable';
    }

    protected function getWhereSQL(): string
    {
        return empty($this->where) ? '' : ' WHERE '.implode(' AND ', $this->where);
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Overrides pagination for export/batch iteration.
     */
    public function setPage(int $page, int $pageSize): self
    {
        $this->page = max(1, $page);
        $this->pageSize = max(1, $pageSize);

        return $this;
    }

    /**
     * Returns the filters that were applied from the query string, for
     * metadata in export and aggregate responses.
     */
    public function getAppliedFilters(): array
    {
        $applied = [];
        foreach ($this->bindings as $key => $value) {
            if (strpos($key, 'f') === 0) {
                $applied[$key] = $value;
            }
        }

        return $applied;
    }

    /**
     * Strips sensitive columns and applies the caller's ?fields= selection.
     */
    public function formatRow(array $row): array
    {
        foreach ($this->resource['sensitive'] as $column) {
            unset($row[$column]);
        }

        if (!empty($this->fields)) {
            $row = array_intersect_key($row, array_flip($this->fields));
        }

        return $row;
    }
}
