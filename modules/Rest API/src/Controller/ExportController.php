<?php
namespace Gibbon\Module\RestAPI\Controller;

use Gibbon\Module\RestAPI\Http\ApiException;
use Gibbon\Module\RestAPI\Http\Request;
use Gibbon\Module\RestAPI\Resource\Registry;
use Gibbon\Module\RestAPI\Support\QueryBuilder;
use Gibbon\Module\RestAPI\Support\Settings;

/**
 * GET /v2/{resource}/export
 *
 * Returns every matching record — all pages, not one — as CSV or JSON.
 * Filters, search, sort and ?fields= all apply exactly as they would on the
 * list endpoint, so an export of /v2/students?tawasulYearGroupID=KG is the
 * same slice of data, just unpaginated.
 *
 *   ?format=csv    Streamed CSV download (sends a file response).
 *   ?format=json   Unpaginated JSON array.
 *   ?batchSize=    Internal fetch batch for streaming (default 1000).
 *
 * A CSV export returns a payload with a __csv key containing a writer closure
 * that the Kernel streams. A JSON export returns a normal payload array.
 */
class ExportController
{
    protected $crud;
    protected $settings;

    public function __construct(CrudController $crud, Settings $settings)
    {
        $this->crud = $crud;
        $this->settings = $settings;
    }

    public function export(string $name, Request $request): array
    {
        $resource = Registry::get($name);
        $this->crud->guard($resource, 'GET');

        $format = strtolower((string) $request->query('format', 'json'));
        $allowedFormats = ['csv', 'json'];

        if (!in_array($format, $allowedFormats, true)) {
            throw ApiException::badRequest(
                'Unsupported export format "'.$format.'".',
                ['allowedFormats' => $allowedFormats]
            );
        }

        $schoolYearID = $this->crud->resolveSchoolYearPublic($request);
        $batchSize = max(100, $request->queryInt('batchSize', 1000));
        $maxBatch = $this->settings->getInt('maxPageSize', 500);
        $batchSize = min($batchSize, $maxBatch * 20);

        $builder = (new QueryBuilder($resource))->applyRequest(
            $request,
            $batchSize,
            $batchSize * 100,
            $schoolYearID,
            $this->crud->personID()
        );

        $totalStmt = $this->crud->getPdo()->prepare($builder->getCountSQL());
        $totalStmt->execute($builder->getBindings());
        $total = (int) $totalStmt->fetchColumn();

        if ($format === 'csv') {
            $pdo = $this->crud->getPdo();
            $resourceName = $resource['name'];

            return [
                '__csv' => [
                    'filename' => $resourceName.'-export-'.date('Y-m-d').'.csv',
                    'writer' => function ($handle) use ($resource, $builder, $total, $pdo) {
                        $batchSize = $builder->getPageSize();
                        $page = 1;
                        $written = 0;
                        $columns = null;

                        while ($written < $total) {
                            $builder->setPage($page, $batchSize);
                            $stmt = $pdo->prepare($builder->getSelectSQL());
                            $stmt->execute($builder->getBindings());
                            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                            if (empty($rows)) {
                                break;
                            }

                            if ($columns === null) {
                                $columns = array_keys($rows[0]);
                                fputcsv($handle, array_merge(['#'], $columns));
                            }

                            foreach ($rows as $row) {
                                $formatted = $builder->formatRow($row);
                                $line = [];
                                foreach ($columns as $col) {
                                    $line[] = self::flatten($formatted[$col] ?? '');
                                }
                                fputcsv($handle, array_merge([($written + 1)], $line));
                                $written++;
                            }

                            if (count($rows) < $batchSize) {
                                break;
                            }

                            $page++;
                            fflush($handle);
                        }
                    },
                ],
                'meta' => [
                    'resource' => $resource['name'],
                    'format' => 'csv',
                    'count' => $total,
                    'batchSize' => $batchSize,
                ],
            ];
        }

        // JSON format: fetch in batches.
        $all = [];
        $page = 1;

        while (count($all) < $total) {
            $builder->setPage($page, $batchSize);
            $stmt = $this->crud->getPdo()->prepare($builder->getSelectSQL());
            $stmt->execute($builder->getBindings());
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $all[] = $builder->formatRow($row);
            }

            if (count($rows) < $batchSize) {
                break;
            }

            $page++;
        }

        return [
            'data' => $all,
            'meta' => [
                'resource' => $resource['name'],
                'format' => 'json',
                'count' => count($all),
                'total' => $total,
                'batchSize' => $batchSize,
            ],
        ];
    }

    /**
     * Converts any value into a plain string suitable for a CSV cell.
     */
    protected static function flatten($value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
