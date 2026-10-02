<?php
namespace Gibbon\Module\RestAPI\Controller;

use Gibbon\Module\RestAPI\Auth\Credential;
use Gibbon\Module\RestAPI\Http\ApiException;
use Gibbon\Module\RestAPI\Http\Request;
use Gibbon\Module\RestAPI\Http\Router;
use Gibbon\Module\RestAPI\Support\Settings;

/**
 * POST /v2/batch
 *
 * Executes multiple API calls in a single HTTP request — the JSON:API /
 * Facebook batch pattern. Every school-management integration starts life as
 * ten little "GET /v2/students/{id}/timetable" round-trips; batch lets the
 * caller do them all at once.
 *
 * Request body:
 *   {
 *     "requests": [
 *       { "method": "GET",  "path": "/v2/students/ABC001" },
 *       { "method": "GET",  "path": "/v2/students/ABC001/timetable" },
 *       { "method": "GET",  "path": "/v2/classes/1A/roster" },
 *       { "method": "PATCH","path": "/v2/students/ABC002", "body": {"status":"Leave"} }
 *     ]
 *   }
 *
 * Response: 207 Multi-Status with per-request status, headers and body.
 *
 * Each sub-request runs with the same credential and scope as the outer
 * request. A failure in one sub-request does not stop the others; the caller
 * gets a status per item, exactly as expected from a batch endpoint.
 */
class BatchController
{
    protected $router;
    protected $dispatcher;
    protected $settings;
    protected $maxRequests;

    /**
     * @param \Closure $dispatcher  function (Request $subRequest, array $route)
     *                               Returns array{status: int, body: array, headers: array}
     */
    public function __construct(Router $router, \Closure $dispatcher, Settings $settings, int $maxRequests = 50)
    {
        $this->router = $router;
        $this->dispatcher = $dispatcher;
        $this->settings = $settings;
        $this->maxRequests = max(1, $maxRequests);
    }

    public function handle(Request $request): array
    {
        $body = $request->getBody();
        $requests = is_array($body) ? ($body['requests'] ?? $body) : [];

        if (!is_array($requests) || empty($requests)) {
            throw ApiException::badRequest(
                'Send a "requests" array with at least one sub-request.',
                ['example' => ['requests' => [['method' => 'GET', 'path' => '/v2/students']]]]
            );
        }

        if (count($requests) > $this->maxRequests) {
            throw ApiException::badRequest(
                'A batch may contain at most '.$this->maxRequests.' sub-requests.',
                ['sent' => count($requests), 'maximum' => $this->maxRequests]
            );
        }

        $results = [];
        $anyFailure = false;

        foreach (array_values($requests) as $index => $item) {
            $result = $this->executeOne($index, $item);
            if ($result['status'] >= 400) {
                $anyFailure = true;
            }
            $results[] = $result;
        }

        return [
            'data' => $results,
            'meta' => [
                'resource' => 'batch',
                'requested' => count($requests),
                'succeeded' => count(array_filter($results, fn($r) => $r['status'] < 400)),
                'failed' => count(array_filter($results, fn($r) => $r['status'] >= 400)),
            ],
            'status' => $anyFailure ? 207 : 200,
        ];
    }

    protected function executeOne(int $index, $item): array
    {
        if (!is_array($item)) {
            return $this->result($index, 400, ['error' => ['code' => 'invalid_request', 'message' => 'Sub-request '.$index.' is not an object.']]);
        }

        $method = strtoupper((string) ($item['method'] ?? 'GET'));
        if (!in_array($method, ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return $this->result($index, 400, ['error' => ['code' => 'method_not_allowed', 'message' => 'Invalid method "'.$method.'" in sub-request '.$index.'.']]);
        }

        $path = (string) ($item['path'] ?? '');
        if ($path === '') {
            return $this->result($index, 400, ['error' => ['code' => 'invalid_request', 'message' => 'Sub-request '.$index.' has no "path".']]);
        }

        $subRequest = Request::forBatch($method, $path, $item['body'] ?? null, $item['headers'] ?? []);

        try {
            $route = $this->router->match($subRequest->getPath(), $method);

            [$status, $body, $headers] = ($this->dispatcher)($subRequest, $route);

            return $this->result($index, $status, $body, $headers);
        } catch (ApiException $e) {
            return $this->result($index, $e->getStatusCode(), $e->toResponse(), $e->getHeaders());
        } catch (\Throwable $e) {
            return $this->result($index, 500, ['error' => ['code' => 'server_error', 'message' => 'Sub-request '.$index.' failed unexpectedly.']]);
        }
    }

    protected function result(int $index, int $status, array $body, array $headers = []): array
    {
        return [
            'index' => $index,
            'status' => $status,
            'headers' => $headers,
            'body' => $body,
        ];
    }
}
