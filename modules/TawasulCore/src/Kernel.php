<?php
namespace Tos\Module\TawasulCore;

use PDO;
use PDOException;
use Throwable;
use Tos\Module\TawasulCore\Auth\Authenticator;
use Tos\Module\TawasulCore\Auth\Credential;
use Tos\Module\TawasulCore\Auth\Permissions;
use Tos\Module\TawasulCore\Controller\AccountingController;
use Tos\Module\TawasulCore\Controller\AuthController;
use Tos\Module\TawasulCore\Controller\AggregateController;
use Tos\Module\TawasulCore\Controller\BatchController;
use Tos\Module\TawasulCore\Controller\BulkController;
use Tos\Module\TawasulCore\Controller\CompositeController;
use Tos\Module\TawasulCore\Controller\CrudController;
use Tos\Module\TawasulCore\Controller\ExportController;
use Tos\Module\TawasulCore\Controller\FileController;
use Tos\Module\TawasulCore\Controller\MetaController;
use Tos\Module\TawasulCore\Domain\ApiKeyGateway;
use Tos\Module\TawasulCore\Domain\ApiLogGateway;
use Tos\Module\TawasulCore\Domain\ApiTokenGateway;
use Tos\Module\TawasulCore\Domain\WebhookGateway;
use Tos\Module\TawasulCore\Http\ApiException;
use Tos\Module\TawasulCore\Http\Request;
use Tos\Module\TawasulCore\Http\Response;
use Tos\Module\TawasulCore\Http\Router;
use Tos\Module\TawasulCore\Support\FileStore;
use Tos\Module\TawasulCore\Support\LoginThrottle;
use Tos\Module\TawasulCore\Support\Settings;
use Tos\Module\TawasulCore\Support\WebhookDispatcher;

/**
 * The request lifecycle: settings, CORS, routing, authentication, dispatch,
 * logging, output.
 *
 * Everything that can go wrong ends up as a single JSON error shape, and the
 * request is written to the log whether it succeeded or not, so an integrator
 * debugging a 403 can see it in TawasulOS rather than guessing. Every response
 * carries an X-Request-Id: quote it in a support message and the matching log
 * line can be found immediately.
 */
class Kernel
{
    const VERSION = '3.4.01';

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
            $this->response->withHeader('X-TawasulOS-API-Version', self::VERSION);

            // Preflight never touches credentials or the database.
            if ($request->getMethod() === 'OPTIONS') {
                $this->response->noContent();
            }

            if (!$this->settings->isOn('apiEnabled', false)) {
                throw ApiException::unavailable('The REST API is switched off for this TawasulOS.');
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
        } catch (PDOException $e) {
            $this->failFromDatabase($request, $route, $credential, $e);
        } catch (Throwable $e) {
            // Never leak a stack trace or SQL to the caller; the log keeps it.
            $this->fail($request, $route, $credential, 500, 'server_error',
                'Something went wrong handling this request.', [], $e->getMessage());
        }
    }

    /**
     * Turns a database failure into the response a caller can act on.
     *
     * A missing table is not a bug in the request, so it must not be reported as
     * "server_error": that tells an integrator their integration is broken when
     * in fact the module they are reading was never installed, or is still
     * mid-upgrade. Several registered modules ship no tables until their
     * manifest has run, and the definitions for them are published either way.
     * Answering 503 with the table name lets the caller tell "not installed"
     * apart from "your request was wrong" without reading the server log.
     */
    protected function failFromDatabase(?Request $request, array $route, ?Credential $credential, PDOException $e): void
    {
        if ($e->getCode() === '42S02') {
            $this->fail(
                $request, $route, $credential, 503, 'schema_incomplete',
                'This endpoint is registered but its table is not present in the database. The module is probably not installed or is mid-upgrade.',
                ['table' => $this->missingTable($e->getMessage())],
                $e->getMessage()
            );

            return;
        }

        $this->fail($request, $route, $credential, 500, 'server_error',
            'Something went wrong handling this request.', [], $e->getMessage());
    }

    /**
     * Pulls the table name out of MySQL's "Base table or view not found" text.
     *
     * The table name is safe to return: it is part of the request the caller
     * already made, and naming it is the only way they can act on the answer.
     */
    protected function missingTable(string $message): ?string
    {
        if (!preg_match('/Base table or view not found/i', $message)) {
            return null;
        }

        if (preg_match("/Table '([^']+)' doesn't exist/i", $message, $matches)
            || preg_match("/'([^']+)'/", $message, $matches)) {
            // The driver qualifies the name with the schema ("tos1.timetable").
            // Only the table itself is worth returning: it names the definition
            // at fault, whereas the schema name is just this install's internal
            // labelling and means nothing to the caller.
            $table = $matches[1];
            $dot = strrpos($table, '.');

            return $dot === false ? $table : substr($table, $dot + 1);
        }

        return null;
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
     * way to hammer this TawasulOS. Tokens now share one configurable ceiling.
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

            case 'accounting.postJournal':
                return $this->accounting($permissions, $credential)->postJournal($request);

            case 'accounting.validateJournal':
                return $this->accounting($permissions, $credential)->validateJournal($request);

            case 'accounting.reverseJournal':
                return $this->accounting($permissions, $credential)->reverseJournal($params['id'], $request);

            case 'accounting.report':
                return $this->accounting($permissions, $credential)->report($params['report'], $request);

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

    protected function accounting(Permissions $permissions, Credential $credential): AccountingController
    {
        return new AccountingController($this->pdo, $permissions, $credential);
    }

    protected function distinct(Permissions $permissions, Credential $credential): CrudController
    {
        return $this->crud($permissions, $credential);
    }

    protected function files(Permissions $permissions, Credential $credential): FileController
    {
        return new FileController(
            $this->crud($permissions, $credential),
            new FileStore($this->settings, $this->tos_absolute_path())
        );
    }

    /**
     * Where this TawasulOS keeps its files. Read from core's own setting so the
     * API and TawasulOS always agree, with the module folder as a fallback for an
     * installation that has not set it.
     */
    protected function tos_absolute_path(): string
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
        $script = $_SERVER['SCRIPT_NAME'] ?? '/modules/TawasulCore/api.php';

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
            'tos_api_keyID' => $credential ? $credential->getKeyID() : null,
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
