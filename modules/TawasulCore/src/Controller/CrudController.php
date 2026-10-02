<?php
namespace Tos\Module\TawasulCore\Controller;

use PDO;
use Tos\Module\TawasulCore\Auth\Credential;
use Tos\Module\TawasulCore\Auth\Permissions;
use Tos\Module\TawasulCore\Http\ApiException;
use Tos\Module\TawasulCore\Http\Request;
use Tos\Module\TawasulCore\Resource\Registry;
use Tos\Module\TawasulCore\Resource\Relations;
use Tos\Module\TawasulCore\Support\QueryBuilder;
use Tos\Module\TawasulCore\Support\Settings;
use Tos\Module\TawasulCore\Support\Validator;
use Tos\Module\TawasulCore\Support\WebhookDispatcher;

/**
 * Serves every registry resource: list, read, create, replace, update, delete.
 *
 * There is deliberately one implementation rather than a controller per area.
 * Behaviour that matters — filtering, pagination, scope checks, school-year
 * scoping, field whitelisting, validation, relation expansion and change
 * events — is then identical on all several hundred endpoints, and the
 * documentation generated from the same registry cannot describe something
 * the code does not do.
 */
class CrudController
{
    protected $pdo;
    protected $settings;
    protected $permissions;
    protected $credential;
    protected $dispatcher;
    protected $relations;

    public function __construct(
        PDO $pdo,
        Settings $settings,
        Permissions $permissions,
        Credential $credential,
        ?WebhookDispatcher $dispatcher = null
    ) {
        $this->pdo = $pdo;
        $this->settings = $settings;
        $this->permissions = $permissions;
        $this->credential = $credential;
        $this->dispatcher = $dispatcher;
        $this->relations = new Relations($pdo, $permissions, $credential);
    }

    public function handle(string $name, ?string $id, Request $request): array
    {
        $resource = Registry::get($name);
        $method = $request->getMethod();

        $this->guard($resource, $method);

        switch ($method) {
            case 'GET':
                return $id === null ? $this->index($resource, $request) : $this->show($resource, $id, $request);
            case 'POST':
                $created = $this->createRecord($resource, $request->getBody());
                $this->fireQueuedEvents();
                return ['data' => $created['data'], 'created' => true];
            case 'PATCH':
            case 'PUT':
                if ($id === null) {
                    throw ApiException::badRequest('An id is required to update a record. Use /'.$resource['name'].'/bulk for batch writes.');
                }
                $updated = $this->updateRecord($resource, $id, $request->getBody(), $method === 'PUT');
                $this->fireQueuedEvents();
                return ['data' => $updated, 'updated' => true];
            case 'DELETE':
                if ($id === null) {
                    throw ApiException::badRequest('An id is required to delete a record. Use /'.$resource['name'].'/bulk for batch deletes.');
                }
                $this->deleteRecord($resource, $id);
                $this->fireQueuedEvents();
                return ['data' => ['deleted' => true, 'id' => $id]];
        }

        throw ApiException::badRequest('Unsupported request.');
    }

    /**
     * Serves /{resource}/{id}/{relation}.
     */
    public function handleRelation(string $name, string $id, string $relation, Request $request): array
    {
        $resource = Registry::get($name);
        $this->guard($resource, 'GET');

        $limit = $request->queryInt('limit', 0);
        $limit = $limit > 0 ? min($limit, $this->settings->getInt('maxPageSize', 500)) : Relations::MAX_CHILDREN;

        return $this->relations->fetchRelation($resource, $id, $relation, $limit);
    }

    /**
     * Every method check in one place: allowed verb, global write switch,
     * module exposure, then scope and TawasulOS role permissions.
     */
    public function guard(array $resource, string $method): void
    {
        if (!in_array($method, $resource['methods'], true)) {
            // A read-only resource says why, because "405" on a table the
            // caller can see in TawasulOS otherwise looks like a bug in the API.
            throw new ApiException(405, 'method_not_allowed',
                !empty($resource['readOnlyReason'])
                    ? $resource['title'].' is read-only. '.$resource['readOnlyReason']
                    : $method.' is not supported on '.$resource['name'].'.',
                array_filter([
                    'allowed' => $resource['methods'],
                    'reason' => $resource['readOnlyReason'] ?? null,
                ])
            );
        }

        if ($method !== 'GET' && !$this->settings->isOn('allowWrites', true)) {
            throw ApiException::forbidden('Write operations are disabled for this TawasulOS.');
        }

        if (!empty($resource['group']) && strpos($resource['group'], 'Modules:') === 0 && !$this->settings->isOn('exposeModules', true)) {
            throw ApiException::notFound('Community module endpoints are disabled for this TawasulOS.');
        }

        if (!empty($resource['generated']) && !$this->settings->isOn('exposeGenerated', true)) {
            throw ApiException::notFound(
                $resource['name'].' is part of the auto-generated schema coverage, which is switched off for this TawasulOS.'
            );
        }

        $this->permissions->authorise($this->credential, $resource, $method);
    }

    protected function index(array $resource, Request $request): array
    {
        $includes = $this->relations->parse($resource, (string) $request->query('include', ''));

        $builder = (new QueryBuilder($resource))->withCurrentPerson($this->credential->getPersonID())->applyRequest(
            $request,
            $this->settings->getInt('defaultPageSize', 50),
            $this->settings->getInt('maxPageSize', 500),
            $this->resolveSchoolYear($request)
        );

        $count = $this->pdo->prepare($builder->getCountSQL());
        $count->execute($builder->getBindings());
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare($builder->getSelectSQL());
        $stmt->execute($builder->getBindings());

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = $builder->formatRow($row);
        }

        if (!empty($includes)) {
            $rows = $this->relations->expand($rows, $includes);
        }

        $pageSize = $builder->getPageSize();

        return [
            'data' => $rows,
            'meta' => [
                'resource' => $resource['name'],
                'page' => $builder->getPage(),
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => $pageSize > 0 ? (int) ceil($total / $pageSize) : 1,
                'included' => array_keys($includes),
            ],
        ];
    }

    protected function show(array $resource, string $id, Request $request): array
    {
        $includes = $this->relations->parse($resource, (string) $request->query('include', ''));
        $row = $this->findRow($resource, $id);

        if (!empty($includes)) {
            $expanded = $this->relations->expand([$row], $includes);
            $row = $expanded[0];
        }

        $result = ['data' => $row];
        if (!empty($includes)) {
            $result['meta'] = ['included' => array_keys($includes)];
        }

        return $result;
    }

    /**
     * Reads one record with every guard applied, for callers outside CRUD such
     * as the file endpoints.
     */
    public function readRecord(string $name, string $id): array
    {
        $resource = Registry::get($name);
        $this->guard($resource, 'GET');

        return ['resource' => $resource, 'row' => $this->findRow($resource, $id)];
    }

    /**
     * Reads a single record through the resource's own SELECT, so the same
     * joins, filters and sensitive-column stripping apply as to a list.
     */
    protected function findRow(array $resource, string $id): array
    {
        $builder = (new QueryBuilder($resource))->withCurrentPerson($this->credential->getPersonID())->whereID($id);

        $stmt = $this->pdo->prepare($builder->getSelectSQL());
        $stmt->execute($builder->getBindings());
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($row)) {
            throw ApiException::notFound('No '.$resource['title'].' record with id '.$id.'.');
        }

        return $builder->formatRow($row);
    }

    /**
     * Creates one record. Shared by POST /{resource} and the bulk endpoint.
     *
     * @return array{id: string, data: array}
     */
    public function createRecord(array $resource, array $body): array
    {
        $data = $this->collectWritable($resource, $body, true);
        $data = Validator::validate($resource, $data, true);
        Validator::checkLengths($resource, $data);

        if (empty($data)) {
            throw ApiException::unprocessable('No writable fields supplied.', ['writableFields' => $resource['writable']]);
        }

        $columns = array_keys($data);
        $sql = 'INSERT INTO '.$resource['table'].' (`'.implode('`, `', $columns).'`) VALUES (:'.implode(', :', $columns).')';

        return $this->atomically(function () use ($resource, $sql, $data) {
            try {
                $this->pdo->prepare($sql)->execute($data);
            } catch (\PDOException $e) {
                throw $this->translateDatabaseError($e);
            }

            $id = (string) $this->pdo->lastInsertId();
            $row = $id !== '' && $id !== '0' ? $this->findRow($resource, $id) : $data;

            $this->emit($resource, 'created', $id, $row);

            return ['id' => $id, 'data' => $row];
        });
    }

    /**
     * Updates one record.
     *
     * PATCH writes only the fields supplied. PUT is a true replace: every
     * required field must be present, and any writable field left out is reset
     * to NULL — so a client that reads a record, edits it and sends it back
     * gets exactly what it sent, with no leftovers from the previous version.
     */
    public function updateRecord(array $resource, string $id, array $body, bool $replace = false): array
    {
        $existing = $this->findRow($resource, $id);

        $data = $this->collectWritable($resource, $body, $replace);
        $data = Validator::validate($resource, $data, $replace);
        Validator::checkLengths($resource, $data);

        if ($replace) {
            foreach ($resource['writable'] as $field) {
                if (!array_key_exists($field, $data)) {
                    if (in_array($field, $resource['required'], true)) {
                        throw ApiException::unprocessable(
                            'PUT replaces the whole record, so '.$field.' must be supplied. Use PATCH to change single fields.',
                            ['errors' => [['field' => $field, 'code' => 'required', 'message' => $field.' is required for a full replace.']]]
                        );
                    }
                    $data[$field] = null;
                }
            }
        }

        if (empty($data)) {
            throw ApiException::unprocessable(
                'No writable fields supplied.',
                ['writableFields' => $resource['writable']]
            );
        }

        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = '`'.$column.'`=:'.$column;
        }
        $data['recordID'] = $id;

        $sql = 'UPDATE '.$resource['table'].' SET '.implode(', ', $assignments)
            .' WHERE '.$resource['primaryKey'].'=:recordID';

        return $this->atomically(function () use ($resource, $id, $sql, $data, $replace, $existing) {
            try {
                $this->pdo->prepare($sql)->execute($data);
            } catch (\PDOException $e) {
                throw $this->translateDatabaseError($e);
            }

            $row = $this->findRow($resource, $id);
            $this->emit($resource, $replace ? 'replaced' : 'updated', $id, $row, $existing);

            return $row;
        });
    }

    public function deleteRecord(array $resource, string $id): void
    {
        $existing = $this->findRow($resource, $id);

        $sql = 'DELETE FROM '.$resource['table'].' WHERE '.$resource['primaryKey'].'=:recordID';

        $this->atomically(function () use ($resource, $id, $sql, $existing) {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['recordID' => $id]);
            } catch (\PDOException $e) {
                throw $this->translateDatabaseError($e);
            }

            if ($stmt->rowCount() === 0) {
                throw ApiException::notFound('No '.$resource['title'].' record with id '.$id.'.');
            }

            $this->emit($resource, 'deleted', $id, $existing);

            return true;
        });
    }

    /**
     * Queues a change event. Nothing is sent until fireQueuedEvents() runs,
     * which happens after the database work has committed.
     */
    protected function emit(array $resource, string $action, string $id, array $record, ?array $previous = null): void
    {
        if ($this->dispatcher === null) {
            return;
        }

        $payload = [
            'resource' => $resource['name'],
            'id' => $id,
            'record' => $record,
            'actor' => [
                'type' => $this->credential->getType(),
                'tawasulPersonID' => $this->credential->getPersonID(),
            ],
        ];

        if ($previous !== null) {
            $payload['previous'] = $previous;
        }

        $this->dispatcher->queue($resource['name'].'.'.$action, $payload);
    }

    public function fireQueuedEvents(): void
    {
        if ($this->dispatcher !== null && $this->dispatcher->hasQueued()) {
            $this->dispatcher->flush();
        }
    }

    /**
     * Keeps only the columns the definition marks writable, so a caller can
     * never set tawasulRoleIDPrimary on a resource that does not offer it.
     */
    protected function collectWritable(array $resource, array $body, bool $requireAll): array
    {
        if (empty($resource['writable'])) {
            throw ApiException::forbidden($resource['title'].' is read-only.');
        }

        $unknown = array_diff(array_keys($body), $resource['writable']);
        if (!empty($unknown)) {
            throw ApiException::unprocessable(
                'Unrecognised fields in request body.',
                ['unknown' => array_values($unknown), 'writableFields' => $resource['writable']]
            );
        }

        $data = [];
        foreach ($resource['writable'] as $field) {
            if (array_key_exists($field, $body)) {
                $value = $body[$field];
                $data[$field] = is_scalar($value) || $value === null ? $value : json_encode($value);
            }
        }

        return $data;
    }

    /**
     * Wraps one write in a transaction.
     *
     * A single INSERT is atomic on its own, but a write here is rarely one
     * statement: the row is written, then re-read, and a file endpoint may move
     * a file in the same breath. If any part throws, this rolls the row back
     * rather than leaving a record that points at nothing. The bulk endpoint
     * opens its own transaction around many records, so nested calls simply
     * join it instead of committing early.
     */
    protected function atomically(callable $work)
    {
        if ($this->pdo->inTransaction()) {
            return $work();
        }

        $this->pdo->beginTransaction();

        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    protected function resolveSchoolYear(Request $request): ?string
    {
        $year = $request->query('tawasulSchoolYearID');
        if (!empty($year)) {
            return (string) $year;
        }

        $header = $request->getHeader('x-school-year');
        if (!empty($header)) {
            return (string) $header;
        }

        if (!empty($this->credential->getSchoolYearID())) {
            return $this->credential->getSchoolYearID();
        }

        $current = $this->pdo->query("SELECT tawasulSchoolYearID FROM tawasulSchoolYear WHERE status='Current' LIMIT 1")->fetchColumn();

        return $current ?: null;
    }

    /**
     * Public accessor so sibling controllers (export, aggregate, batch) can
     * reuse the PDO connection and the school-year resolution without
     * reaching into protected internals.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getCredentialPersonID(): ?string
    {
        return $this->credential->getPersonID();
    }

    public function resolveSchoolYearPublic(Request $request): ?string
    {
        return $this->resolveSchoolYear($request);
    }

    /**
     * Serves /{resource}/distinct?field=columnAlias
     *
     * Returns all distinct values for a given field, useful for populating
     * dropdowns in client applications. The field is resolved through the
     * resource's filters and sort maps, same as ?filter= and ?sort=.
     */
    public function distinct(string $name, Request $request): array
    {
        $resource = Registry::get($name);
        $this->guard($resource, 'GET');

        $field = (string) $request->query('field', '');
        if ($field === '') {
            throw ApiException::badRequest(
                'A field parameter is required: ?field=alias',
                ['availableFilters' => array_keys($resource['filters'] ?? []), 'availableSorts' => array_keys($resource['sort'] ?? [])]
            );
        }

        $column = $this->resolveColumn($resource, $field);
        if ($column === null) {
            throw ApiException::badRequest(
                'Cannot get distinct values for "'.$field.'".',
                ['availableFilters' => array_keys($resource['filters'] ?? []), 'availableSorts' => array_keys($resource['sort'] ?? [])]
            );
        }

        $schoolYearID = $this->resolveSchoolYear($request);

        $builder = (new QueryBuilder($resource))->withCurrentPerson($this->credential->getPersonID())->applyRequest(
            $request,
            $this->settings->getInt('defaultPageSize', 50),
            $this->settings->getInt('maxPageSize', 500),
            $schoolYearID
        );

        // The definition's select is wrapped as a derived table, so it only
        // exposes the bare column names it selected. Referring to the qualified
        // name out here ("tawasulFinanceAccount.type") is not in scope, which is
        // why the table prefix is stripped before it is used.
        $exposed = substr(strrchr('.'.$column, '.'), 1);

        $sql = 'SELECT DISTINCT '.$exposed.' AS value FROM ('.$resource['select'].$builder->getWhereSQL().') AS data ORDER BY '.$exposed;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($builder->getBindings());

        $values = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $values[] = array_values($row)[0];
        }

        return [
            'data' => $values,
            'meta' => [
                'resource' => $resource['name'],
                'field' => $field,
                'count' => count($values),
            ],
        ];
    }

    /**
     * Resolves a field alias to a qualified column name.
     */
    protected function resolveColumn(array $resource, string $field): ?string
    {
        if (isset($resource['filters'][$field])) {
            return $resource['filters'][$field];
        }
        if (isset($resource['sort'][$field])) {
            return $resource['sort'][$field];
        }
        if (strpos($field, '.') !== false) {
            return $field;
        }

        return null;
    }

    protected function translateDatabaseError(\PDOException $e): ApiException
    {
        $message = $e->getMessage();

        if (strpos($message, '1062') !== false || stripos($message, 'Duplicate entry') !== false) {
            return ApiException::conflict('A record with these details already exists.');
        }
        if (strpos($message, '1451') !== false || stripos($message, 'a foreign key constraint fails') !== false) {
            return ApiException::conflict('Other records still refer to this one, so it cannot be deleted.');
        }
        if (strpos($message, '1452') !== false || stripos($message, 'foreign key') !== false) {
            return ApiException::unprocessable('A referenced record does not exist.');
        }
        if (strpos($message, '1048') !== false || stripos($message, 'cannot be null') !== false) {
            return ApiException::unprocessable('A required field was left empty.');
        }
        if (strpos($message, '1406') !== false || stripos($message, 'Data too long') !== false) {
            return ApiException::unprocessable('A value is too long for its field.');
        }
        if (strpos($message, '1292') !== false || stripos($message, 'Incorrect date') !== false || stripos($message, 'Incorrect datetime') !== false) {
            return ApiException::unprocessable('A date or time value is not in a format the database accepts.');
        }

        return new ApiException(500, 'database_error', 'The database rejected this request.');
    }
}
