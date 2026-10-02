<?php
namespace Tos\Module\TawasulCore\Controller;

use PDO;
use Tos\Module\TawasulCore\Http\ApiException;
use Tos\Module\TawasulCore\Http\Request;
use Tos\Module\TawasulCore\Resource\Registry;

/**
 * Batch writes: POST/PATCH/DELETE /v2/{resource}/bulk.
 *
 * Taking a register, importing a markbook or syncing enrolments means writing
 * dozens of rows that belong together. Doing that one HTTP call at a time is
 * slow and, worse, can leave half a register saved. This endpoint accepts an
 * array of records and, by default, runs them in a single transaction: either
 * every row lands or none does. Send "atomic": false to keep the successful
 * rows and receive a per-row error report instead.
 *
 *   POST /v2/attendance/bulk
 *   { "records": [ {...}, {...} ], "atomic": true }
 *
 *   PATCH /v2/attendance/bulk
 *   { "records": [ { "id": "0001", "attendance": "Present" } ] }
 *
 *   DELETE /v2/attendance/bulk
 *   { "ids": ["0001", "0002"] }
 */
class BulkController
{
    protected $crud;
    protected $pdo;
    protected $maxItems;

    public function __construct(CrudController $crud, PDO $pdo, int $maxItems)
    {
        $this->crud = $crud;
        $this->pdo = $pdo;
        $this->maxItems = max(1, $maxItems);
    }

    public function handle(string $name, Request $request): array
    {
        $resource = Registry::get($name);
        $method = $request->getMethod();

        if (!in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            throw new ApiException(405, 'method_not_allowed',
                'Bulk endpoints accept POST, PATCH, PUT and DELETE.',
                ['allowed' => ['POST', 'PATCH', 'PUT', 'DELETE']]
            );
        }

        $this->crud->guard($resource, $method === 'DELETE' ? 'DELETE' : 'POST');

        $body = $request->getBody();
        $atomic = !array_key_exists('atomic', $body) || !empty($body['atomic']);
        $items = $method === 'DELETE'
            ? array_map(function ($id) { return ['id' => $id]; }, (array) ($body['ids'] ?? []))
            : (array) ($body['records'] ?? []);

        if (empty($items)) {
            throw ApiException::unprocessable(
                $method === 'DELETE'
                    ? 'Send an "ids" array of record ids to delete.'
                    : 'Send a "records" array of objects to write.'
            );
        }

        if (count($items) > $this->maxItems) {
            throw ApiException::unprocessable(
                'A bulk request may contain at most '.$this->maxItems.' records.',
                ['sent' => count($items), 'maximum' => $this->maxItems]
            );
        }

        $started = $atomic ? $this->beginTransaction() : false;

        $results = [];
        $succeeded = 0;
        $failed = 0;

        foreach (array_values($items) as $index => $item) {
            try {
                if (!is_array($item)) {
                    throw ApiException::unprocessable('Record '.$index.' is not an object.');
                }

                $result = $this->applyOne($resource, $method, $item, $index);
                $results[] = ['index' => $index, 'status' => 'ok'] + $result;
                $succeeded++;
            } catch (ApiException $e) {
                $failed++;
                $results[] = [
                    'index' => $index,
                    'status' => 'error',
                    'error' => [
                        'code' => $e->getErrorCode(),
                        'message' => $e->getMessage(),
                        'details' => $e->getDetails(),
                    ],
                ];

                if ($atomic) {
                    if ($started) {
                        $this->pdo->rollBack();
                    }

                    throw new ApiException(422, 'bulk_failed',
                        'Record '.$index.' failed, so no records were written. Send "atomic": false to keep the rows that do succeed.',
                        ['failedIndex' => $index, 'reason' => $e->getMessage(), 'results' => $results]
                    );
                }
            }
        }

        if ($started) {
            $this->pdo->commit();
        }

        $this->crud->fireQueuedEvents();

        return [
            'data' => $results,
            'meta' => [
                'resource' => $resource['name'],
                'operation' => strtolower($method),
                'atomic' => $atomic,
                'submitted' => count($items),
                'succeeded' => $succeeded,
                'failed' => $failed,
            ],
        ];
    }

    protected function applyOne(array $resource, string $method, array $item, int $index): array
    {
        switch ($method) {
            case 'POST':
                $created = $this->crud->createRecord($resource, $item);
                return ['id' => $created['id'], 'data' => $created['data']];

            case 'PATCH':
            case 'PUT':
                $id = (string) ($item['id'] ?? $item[$resource['primaryKey']] ?? '');
                if ($id === '') {
                    throw ApiException::unprocessable('Record '.$index.' needs an "id" to update.');
                }
                unset($item['id'], $item[$resource['primaryKey']]);
                $updated = $this->crud->updateRecord($resource, $id, $item, $method === 'PUT');
                return ['id' => $id, 'data' => $updated];

            case 'DELETE':
                $id = (string) ($item['id'] ?? '');
                if ($id === '') {
                    throw ApiException::unprocessable('Entry '.$index.' is not a valid record id.');
                }
                $this->crud->deleteRecord($resource, $id);
                return ['id' => $id, 'deleted' => true];
        }

        throw ApiException::badRequest('Unsupported bulk operation.');
    }

    protected function beginTransaction(): bool
    {
        if ($this->pdo->inTransaction()) {
            return false;
        }

        return $this->pdo->beginTransaction();
    }
}
