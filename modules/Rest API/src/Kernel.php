<?php
namespace Gibbon\Module\RestAPI;

use PDO;
use Throwable;
use Gibbon\Module\RestAPI\Auth\Authenticator;
use Gibbon\Module\RestAPI\Auth\Credential;
use Gibbon\Module\RestAPI\Auth\Permissions;
use Gibbon\Module\RestAPI\Controller\AuthController;
use Gibbon\Module\RestAPI\Controller\AggregateController;
use Gibbon\Module\RestAPI\Controller\BatchController;
use Gibbon\Module\RestAPI\Controller\BulkController;
use Gibbon\Module\RestAPI\Controller\CompositeController;
use Gibbon\Module\RestAPI\Controller\CrudController;
use Gibbon\Module\RestAPI\Controller\ExportController;
use Gibbon\Module\RestAPI\Controller\FileController;
use Gibbon\Module\RestAPI\Controller\MetaController;
use Gibbon\Module\RestAPI\Domain\ApiKeyGateway;
use Gibbon\Module\RestAPI\Domain\ApiLogGateway;
use Gibbon\Module\RestAPI\Domain\ApiTokenGateway;
use Gibbon\Module\RestAPI\Domain\WebhookGateway;
use Gibbon\Module\RestAPI\Http\ApiException;
use Gibbon\Module\RestAPI\Http\Request;
use Gibbon\Module\RestAPI\Http\Response;
use Gibbon\Module\RestAPI\Http\Router;
use Gibbon\Module\RestAPI\Support\FileStore;
use Gibbon\Module\RestAPI\Support\LoginThrottle;
use Gibbon\Module\RestAPI\Support\Settings;
use Gibbon\Module\RestAPI\Support\WebhookDispatcher;

/**
 * The request lifecycle: settings, CORS, routing, authentication, dispatch,
 * logging, output.
 *
 * Everything that can go wrong ends up as a single JSON error shape, and the
 * request is written to the log whether it succeeded or not, so an integrator
 * debugging a 403 can see it in Gibbon rather than guessing. Every response
 * carries an X-Request-Id: quote it in a support message and the matching log
 * line can be found immediately.
 */
class Kernel
{
    const VERSION = '3.3.03';

    protected $pdo;
    protected $settings;
    protected $response;
    protected $startedAt;
    protected $requestID;
    protected $dispatcher;
    protected $throttle;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->startedAt = microtime(true);
        $this->settings = new Settings($pdo);
        $this->response = new Response($this->settings);
        $this->requestID = 'req_'.bin2hex(random_bytes(8));
        $this->dispatcher = new WebhookDispatcher($pdo, $this->settings);
        $this->throttle = new LoginThrottle($pdo, $this->settings);
    }

    public function handle(): void
    {
        $request = null;
        $credential = null;
        $route = ['name' => 'unknown', 'params' => []];

        try {
            $request = Request::capture();
            $this->response->sendCorsHeaders($request);
            $this->response->withHeader('X-Request-Id', $this->requestID);
            $this->response->withHeader('X-Gibbon-API-Version', self::VERSION);

            // Preflight never touches credentials or the database.
            if ($request->getMethod() === 'OPTIONS') {
                $this->response->noContent();
            }

            if (!$this->settings->isOn('apiEnabled', false)) {
                throw ApiException::unavailable('The REST API is switched off for this Gibbon.');
            }

            $route = (new Router())->match($request->getPath(), $request->getMethod());

            // The credential endpoints are the only unauthenticated writes in
            // the API, so they are throttled per address before a password is
            // ever checked.
            if (in_array($route['name'], ['auth.login', 'auth.refresh'], true)) {
                $this->throttle->assertAllowed($request->getClientIP(), $route['name']);
            }

            if (!$route['public']) {
                $credential = $this->authenticate($request);
                $this->throttleUserToken($credential);
            }

            $payload = $this->dispatch($route, $request, $credential ?: new Credential('anonymous'));

            // A file download is handled here rather than in the controller so
            // that logging and rate-limit headers still happen first.
            if (is_array($payload) && isset($payload['__file'])) {
                $this->log($request, $route, $credential, 200, '');
                $this->response->sendFile($payload['__file'], !empty($payload['inline']));
            }

            // A CSV export is streamed through a writer closure (see ExportController).
            if (is_array($payload) && isset($payload['__csv'])) {
                $this->log($request, $route, $credential, 200, '');
                $this->response->csv($payload['__csv']['filename'], $payload['__csv']['writer']);
            }

            $status = !empty($payload['created']) ? 201 : 200;
            // Allow controllers to override the HTTP status (e.g. 207 Multi-Status).
            if (isset($payload['status'])) {
                $status = (int) $payload['status'];
            }
            unset($payload['created'], $payload['updated'], $payload['status']);

            if (is_array($payload)) {
                $payload['meta'] = array_merge($payload['meta'] ?? [], ['requestID' => $this->requestID]);
            }

            $this->log($request, $route, $credential, $status, '');
            $this->housekeeping();
            $this->response->json($payload, $status);
        } catch (ApiException $e) {
            $this->fail($request, $route, $credential, $e->getStatusCode(), $e->getErrorCode(), $e->getMessage(), $e->getDetails());
        } catch (Throwable $e) {
            // Never leak a stack trace or SQL to the caller; the log keeps it.
            $this->fail($request, $route, $credential, 500, 'server_error',
                'Something went wrong handling this request.', [], $e->getMessage());
        }
    }

    protected function authenticate(Request $request): Credential
    {
        $authenticator = new Authenticator($this->pdo, new ApiKeyGateway($this->pdo), new ApiTokenGateway($this->pdo));

        try {
            $credential = $authenticator->authenticate($request);
        } finally {
            $this->sendRateLimitHeaders($authenticator);
        }

        return $credential;
    }

    /**
     * API keys carry their own per-minute limit, but a token minted from a
     * username and password had none, so the password grant was the cheapest
     * way to hammer this Gibbon. Tokens now share one configurable ceiling.
     */
    protected function throttleUserToken(Credential $credential): void
    {
        $limit = $this->settings->getInt('tokenRateLimit', 240);

        if ($limit <= 0 || $credential->getKeyID() !== null || $credential->getPersonID() === null) {
            return;
        }

        $used = $this->throttle->countRecentRequestsForPerson((string) $credential->getPersonID());

        $this->response->withHeader('X-RateLimit-Limit', (string) $limit);
        $this->response->withHeader('X-RateLimit-Remaining', (string) max(0, $limit - $used));
        $this->response->withHeader('X-RateLimit-Reset', (string) (time() + 60));

        if ($used >= $limit) {
            throw ApiException::rateLimited(
                'This sign-in has made too many requests in the last minute.',
                ['limit' => $limit, 'used' => $used]
            );
        }
    }

    protected function sendRateLimitHeaders(Authenticator $authenticator): void
    {
        $info = $authenticator->getRateLimitInfo();
        if ($info['limit'] === null) {
            return;
        }

        $this->response->withHeader('X-RateLimit-Limit', (string) $info['limit']);
        $this->response->withHeader('X-RateLimit-Remaining', (string) $info['remaining']);
        $this->response->withHeader('X-RateLimit-Reset', (string) (time() + 60));
    }

    protected function dispatch(array $route, Request $request, Credential $credential)
    {
        $permissions = new Permissions($this->pdo, $this->settings->isOn('enforceRolePermissions', true));
        $params = $route['params'];

        switch ($route['name']) {
            case 'meta.index':
                return $this->meta()->index();
            case 'meta.health':
                return $this->meta()->health();
            case 'meta.resources':
                return $this->meta()->resources();
            case 'meta.scopes':
                return $this->meta()->scopes();
            case 'meta.events':
                return $this->meta()->events();
            case 'meta.dashboard':
                return $this->meta()->dashboard($this->settings->isOn('apiEnabled', false));

            case 'meta.permissions':
                return $this->meta()->permissions();

            case 'meta.stats':
                return $this->meta()->stats();

            case 'meta.analytics':
                return $this->meta()->analytics($request);

            case 'meta.openapi':
                return $this->meta()->openapi();

            case 'auth.login':
                return $this->auth()->login($request);
            case 'auth.refresh':
                return $this->auth()->refresh($request);
            case 'auth.logout':
                return $this->auth()->logout($credential);
            case 'auth.me':
                return $this->auth()->me($credential);

            case 'composite.studentProfile':
                return (new CompositeController($this->pdo, $permissions, $credential))->studentProfile($params['id'], $request);
            case 'composite.personTimetable':
                return (new CompositeController($this->pdo, $permissions, $credential))->personTimetable($params['id'], $request);
            case 'composite.classRoster':
                return (new CompositeController($this->pdo, $permissions, $credential))->classRoster($params['id']);
            case 'composite.staffProfile':
                return (new CompositeController($this->pdo, $permissions, $credential))->staffProfile($params['id'], $request);
            case 'composite.familyProfile':
                return (new CompositeController($this->pdo, $permissions, $credential))->familyProfile($params['id']);
            case 'composite.courseOverview':
                return (new CompositeController($this->pdo, $permissions, $credential))->courseOverview($params['id']);
            case 'composite.financeSummary':
                return (new CompositeController($this->pdo, $permissions, $credential))->financeSummary($params['id'], $request);

            case 'file.index':
                return $this->files($permissions, $credential)->index($params['resource'], $params['id']);
            case 'file.download':
                return $this->files($permissions, $credential)->download($params['resource'], $params['id'], $params['field'], $request);
            case 'file.upload':
                return $this->files($permissions, $credential)->upload($params['resource'], $params['id'], $params['field'], $request);
            case 'file.detach':
                return $this->files($permissions, $credential)->detach($params['resource'], $params['id'], $params['field']);

            case 'resource':
                return $this->crud($permissions, $credential)
                    ->handle($params['resource'], $params['id'], $request);

            case 'resource.relation':
                return $this->crud($permissions, $credential)
                    ->handleRelation($params['resource'], $params['id'], $params['relation'], $request);

            case 'resource.bulk':
                $crud = $this->crud($permissions, $credential);
                return (new BulkController($crud, $this->pdo, $this->settings->getInt('bulkMaxItems', 200)))
                    ->handle($params['resource'], $request);

            case 'resource.export':
                return $this->export($permissions, $credential)->export($params['resource'], $request);

            case 'resource.aggregate':
                return $this->aggregate($permissions, $credential)->aggregate($params['resource'], $request);

            case 'resource.distinct':
                return $this->distinct($permissions, $credential)
                    ->distinct($params['resource'], $request);

            case 'batch.handle':
                $batch = new BatchController(
                    new Router(),
                    function (Request $subRequest, array $route) use ($credential) {
                        $result = $this->dispatch($route, $subRequest, $credential);

                        // File/CSV sub-responses can't be embedded in a batch
                        // envelope — surface the metadata instead.
                        if (is_array($result) && isset($result['__file'])) {
                            $result = ['data' => ['file' => $result['__file'], 'inline' => !empty($result['inline'])]];
                        }
                        if (is_array($result) && isset($result['__csv'])) {
                            $result = ['data' => ['csv' => $result['__csv']['filename']]];
                        }

                        $isCreated = !empty($result['created']);
                        $status = $isCreated ? 201 : 200;
                        unset($result['created'], $result['updated']);

                        return [$status, $result, []];
                    },
                    $this->settings
                );
                return $batch->handle($request);
        }

        throw ApiException::notFound('That endpoint does not exist.');
    }

    protected function crud(Permissions $permissions, Credential $credential): CrudController
    {
        return new CrudController($this->pdo, $this->settings, $permissions, $credential, $this->dispatcher);
    }

    protected function export(Permissions $permissions, Credential $credential): ExportController
    {
        return new ExportController(
            $this->crud($permissions, $credential),
            $this->settings
        );
    }

    protected function aggregate(Permissions $permissions, Credential $credential): AggregateController
    {
        return new AggregateController($this->crud($permissions, $credential));
    }

    protected function distinct(Permissions $permissions, Credential $credential): CrudController
    {
        return $this->crud($permissions, $credential);
    }

    protected function files(Permissions $permissions, Credential $credential): FileController
    {
        return new FileController(
            $this->crud($permissions, $credential),
            new FileStore($this->settings, $this->tawasulAbsolutePath())
        );
    }

    /**
     * Where this Gibbon keeps its files. Read from core's own setting so the
     * API and Gibbon always agree, with the module folder as a fallback for an
     * installation that has not set it.
     */
    protected function tawasulAbsolutePath(): string
    {
        $path = $this->pdo
            ->query("SELECT value FROM tawasulSetting WHERE scope='System' AND name='absolutePath' LIMIT 1")
            ->fetchColumn();

        return $path ?: dirname(__DIR__, 3);
    }

    protected function meta(): MetaController
    {
        return new MetaController($this->pdo, $this->baseUrl(), self::VERSION);
    }

    protected function auth(): AuthController
    {
        return new AuthController($this->pdo, $this->settings, new ApiTokenGateway($this->pdo));
    }

    protected function baseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '/modules/Rest API/api.php';

        return $scheme.'://'.$host.str_replace(' ', '%20', $script).'/v2';
    }

    /**
     * Log tables grow forever unless something trims them. Rather than depend
     * on a cron job the school may never set up, roughly one request in a
     * hundred prunes anything older than the configured retention period.
     */
    protected function housekeeping(): void
    {
        if (random_int(1, 100) !== 1) {
            return;
        }

        $days = $this->settings->getInt('logRetentionDays', 30);
        if ($days > 0) {
            (new ApiLogGateway($this->pdo))->prune($days);
            (new WebhookGateway($this->pdo))->pruneDeliveries($days);
        }

        (new ApiTokenGateway($this->pdo))->pruneExpired();
    }

    protected function fail(?Request $request, array $route, ?Credential $credential, int $status, string $code, string $message, array $details = [], string $internal = ''): void
    {
        $this->log($request, $route, $credential, $status, $internal !== '' ? $internal : $message);

        $body = ['error' => array_filter([
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ]), 'meta' => ['requestID' => $this->requestID]];

        $this->response->json($body, $status);
    }

    protected function log(?Request $request, array $route, ?Credential $credential, int $status, string $message): void
    {
        // Credential attempts and rejected requests are always recorded, even
        // with request logging switched off: the throttles count them, so
        // skipping them would quietly disable the lockout.
        $security = strpos($route['name'], 'auth.') === 0 || $status === 401 || $status === 403 || $status === 429;

        if (!$security && !$this->settings->isOn('logRequests', true)) {
            return;
        }

        (new ApiLogGateway($this->pdo))->record([
            'restApiKeyID' => $credential ? $credential->getKeyID() : null,
            'tawasulPersonID' => $credential ? $credential->getPersonID() : null,
            'method' => $request ? $request->getMethod() : '',
            'endpoint' => $request ? $request->getPath() : '',
            'resource' => $route['params']['resource'] ?? $route['name'],
            'statusCode' => $status,
            'durationMS' => (int) round((microtime(true) - $this->startedAt) * 1000),
            'message' => mb_substr($message, 0, 255),
            'ipAddress' => $request ? $request->getClientIP() : '',
        ]);
    }
}
