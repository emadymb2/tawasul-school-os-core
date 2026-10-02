<?php
namespace Gibbon\Module\RestAPI\Controller;

use PDO;
use Gibbon\Module\RestAPI\Http\Request;
use Gibbon\Module\RestAPI\Resource\Registry;
use Gibbon\Module\RestAPI\Support\OpenApi;

/**
 * Discovery endpoints: what this API is, whether it is up, and what it offers.
 */
class MetaController
{
    protected $pdo;
    protected $baseUrl;
    protected $moduleVersion;

    public function __construct(PDO $pdo, string $baseUrl, string $moduleVersion)
    {
        $this->pdo = $pdo;
        $this->baseUrl = $baseUrl;
        $this->moduleVersion = $moduleVersion;
    }

    public function index(): array
    {
        return ['data' => [
            'name' => 'Gibbon REST API',
            'version' => $this->moduleVersion,
            'baseUrl' => $this->baseUrl,
            'resourceCount' => count(Registry::all()),
            'documentation' => $this->baseUrl.'/openapi.json',
                'endpoints' => [
                    'health' => $this->baseUrl.'/health',
                    'resources' => $this->baseUrl.'/resources',
                    'scopes' => $this->baseUrl.'/scopes',
                    'login' => $this->baseUrl.'/auth/login',
                    'me' => $this->baseUrl.'/auth/me',
                    'dashboard' => $this->baseUrl.'/dashboard',
                    'permissions' => $this->baseUrl.'/permissions',
                    'stats' => $this->baseUrl.'/stats',
                    'analytics' => $this->baseUrl.'/analytics',
                    'batch' => $this->baseUrl.'/batch (POST)',
                    'events' => $this->baseUrl.'/events',
                    'export' => $this->baseUrl.'/{resource}/export',
                    'aggregate' => $this->baseUrl.'/{resource}/aggregate',
                    'distinct' => $this->baseUrl.'/{resource}/distinct',
                    'files' => $this->baseUrl.'/files/{resource}/{id}/{field}',
                ],
        ]];
    }

    public function health(): array
    {
        $database = 'ok';
        try {
            $this->pdo->query('SELECT 1');
        } catch (\Throwable $e) {
            $database = 'unreachable';
        }

        return ['data' => [
            'status' => $database === 'ok' ? 'ok' : 'degraded',
            'database' => $database,
            'version' => $this->moduleVersion,
            'time' => date('c'),
        ]];
    }

    /**
     * Live control-dashboard snapshot: school structure, students, staff and
     * the API's own sync health. Mirrors the in-Gibbon dashboard screen.
     */
    public function dashboard(bool $apiEnabled = true): array
    {
        return ['data' => (new \Gibbon\Module\RestAPI\Domain\DashboardGateway($this->pdo))->snapshot($apiEnabled)];
    }


    public function resources(): array
    {
        return ['data' => OpenApi::routeTable()];
    }

    /**
     * Which Gibbon screen permission guards each endpoint. Reads are checked
     * against "action", writes against "writeAction"; "unmapped" endpoints have
     * no owning screen and fall back to scope plus a linked Gibbon user.
     */
    public function permissions(): array
    {
        $rows = [];
        $mapped = 0;

        foreach (Registry::all() as $name => $resource) {
            $hasAction = !empty($resource['action']);
            if ($hasAction) {
                $mapped++;
            }

            $rows[] = [
                'resource' => $name,
                'scope' => $resource['scope'],
                'module' => $resource['module'],
                'readAction' => $resource['action'],
                'writeAction' => !empty($resource['writeAction']) ? $resource['writeAction'] : $resource['action'],
                'confidence' => $hasAction ? ($resource['actionConfidence'] ?: 'curated') : 'unmapped',
                'methods' => $resource['methods'],
            ];
        }

        return [
            'meta' => [
                'resources' => count($rows),
                'mapped' => $mapped,
                'unmapped' => count($rows) - $mapped,
            ],
            'data' => $rows,
        ];
    }


    public function scopes(): array
    {
        $scopes = [];
        foreach (Registry::scopes() as $scope => $description) {
            $scopes[] = ['scope' => $scope, 'description' => $description];
        }

        return ['data' => $scopes];
    }

    public function openapi(): array
    {
        return OpenApi::build($this->baseUrl, $this->moduleVersion);
    }

    /**
     * Request analytics derived from the REST API log tables.
     *
     * Returns the same data the API Insights module dashboard shows in-Gibbon,
     * but as structured JSON so external monitoring tools can consume it. All
     * counts are scoped to the last 24 hours by default (override with the
     * `days` query parameter, max 365). Requires the meta.read scope.
     */
    public function analytics(Request $request): array
    {
        $days = max(1, min(365, (int) $request->query('days', 1)));

        $value = function (string $sql, array $bindings = [], $default = 0) {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($bindings);
                $result = $stmt->fetchColumn();
                return $result === false ? $default : $result;
            } catch (\PDOException $e) {
                return $default;
            }
        };

        $query = function (string $sql, array $bindings = []): array {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($bindings);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $e) {
                return [];
            }
        };

        $sinceDate = date('Y-m-d H:i:s', strtotime('-'.$days.' days'));
        $bindings = [':sinceDate' => $sinceDate];

        $total = (int) $value("SELECT COUNT(*) FROM restApiLog WHERE timestamp >= :sinceDate", $bindings);
        $errors = (int) $value("SELECT COUNT(*) FROM restApiLog WHERE timestamp >= :sinceDate AND statusCode >= 400", $bindings);
        $writes = (int) $value("SELECT COUNT(*) FROM restApiLog WHERE timestamp >= :sinceDate AND method IN ('POST','PATCH','PUT','DELETE')", $bindings);
        $avgDuration = (int) $value("SELECT AVG(durationMS) FROM restApiLog WHERE timestamp >= :sinceDate", $bindings);

        $daily = $query(
            "SELECT DATE(timestamp) AS day, COUNT(*) AS requests,
                SUM(CASE WHEN statusCode >= 400 THEN 1 ELSE 0 END) AS errors,
                SUM(CASE WHEN method IN ('POST','PATCH','PUT','DELETE') THEN 1 ELSE 0 END) AS writes
            FROM restApiLog WHERE timestamp >= :sinceDate
            GROUP BY DATE(timestamp) ORDER BY day",
            $bindings
        );

        $busiest = $query(
            "SELECT resource, COUNT(*) AS total,
                SUM(CASE WHEN statusCode >= 400 THEN 1 ELSE 0 END) AS errors
            FROM restApiLog WHERE timestamp >= :sinceDate AND resource <> ''
            GROUP BY resource ORDER BY total DESC LIMIT 10",
            $bindings
        );

        $statusCodes = $query(
            "SELECT statusCode, COUNT(*) AS total FROM restApiLog
            WHERE timestamp >= :sinceDate GROUP BY statusCode ORDER BY total DESC",
            $bindings
        );

        $methods = $query(
            "SELECT method, COUNT(*) AS total FROM restApiLog
            WHERE timestamp >= :sinceDate GROUP BY method ORDER BY total DESC",
            $bindings
        );

        return [
            'data' => [
                'days' => $days,
                'totalRequests' => $total,
                'errors' => $errors,
                'errorRate' => $total > 0 ? round(($errors / $total) * 100, 2) : 0,
                'writes' => $writes,
                'averageDurationMS' => $avgDuration,
                'daily' => $daily,
                'busiest' => $busiest,
                'statusCodes' => $statusCodes,
                'methods' => $methods,
            ],
            'meta' => ['generated' => date('c')],
        ];
    }

    /**
     * Lists every change event a webhook can subscribe to, derived from the
     * resource registry so the list never drifts from the resources themselves.
     */
    public function events(): array
    {
        $events = [];
        foreach (Registry::events() as $event => $description) {
            $events[] = ['event' => $event, 'description' => $description];
        }

        return ['data' => $events];
    }

    /**
     * Returns aggregate statistics for a specific resource or school-wide.
     *
     * When a resource name is given, returns aggregate metrics for that resource
     * (count, methods, scope). Without a resource, returns school-wide counts
     * derived from the dashboard gateway.
     */
    public function stats(?string $resource = null): array
    {
        if ($resource !== null && Registry::has($resource)) {
            $res = Registry::get($resource);
            $sql = 'SELECT COUNT(*) FROM ('.$res['select'].') AS countable';
            $total = (int) $this->pdo->query($sql)->fetchColumn();

            return [
                'data' => [
                    'resource' => $res['name'],
                    'count' => $total,
                    'methods' => $res['methods'],
                    'scope' => $res['scope'],
                ],
                'meta' => [
                    'resourceType' => 'resource',
                ],
            ];
        }

        return ['data' => (new \Gibbon\Module\RestAPI\Domain\DashboardGateway($this->pdo))->snapshot(false), 'meta' => ['resourceType' => 'school']];
    }
}
