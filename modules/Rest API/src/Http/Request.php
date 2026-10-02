<?php
namespace Gibbon\Module\RestAPI\Http;

/**
 * An immutable snapshot of the incoming HTTP request.
 *
 * Gibbon is usually reached at /modules/Rest%20API/api.php, so the endpoint
 * path is read from PATH_INFO with a ?endpoint= fallback for servers that do
 * not expose it.
 */
class Request
{
    protected $method;
    protected $path;
    protected $query = [];
    protected $body = [];
    protected $headers = [];

    public static function capture(): self
    {
        $request = new self();

        $request->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $request->headers = self::readHeaders();

        // Allow tunnelling PATCH/DELETE through POST for restrictive clients.
        if ($request->method === 'POST') {
            $override = strtoupper((string) ($request->headers['x-http-method-override'] ?? ''));
            if (in_array($override, ['PATCH', 'PUT', 'DELETE'], true)) {
                $request->method = $override;
            }
        }

        $path = $_SERVER['PATH_INFO'] ?? '';
        if ($path === '' && !empty($_GET['endpoint'])) {
            $path = (string) $_GET['endpoint'];
        }
        $path = '/'.trim(preg_replace('#[^A-Za-z0-9_\-/\.]#', '', urldecode($path)), '/');
        $request->path = $path === '/' ? '/' : rtrim($path, '/');

        $request->query = is_array($_GET) ? $_GET : [];
        unset($request->query['endpoint'], $request->query['path']);

        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE && stripos((string) ($request->headers['content-type'] ?? ''), 'json') !== false) {
                throw ApiException::badRequest('The request body is not valid JSON.');
            }
            $request->body = is_array($decoded) ? $decoded : [];
        }
        if (empty($request->body) && !empty($_POST)) {
            $request->body = $_POST;
        }

        return $request;
    }

    /**
     * Factory for sub-requests inside a batch call. Mirrors the path/query
     * parsing of capture() but takes everything from the sub-request spec
     * instead of globals.
     */
    public static function forBatch(string $method, string $uri, ?array $body, array $headers): self
    {
        $request = new self();

        $request->method = strtoupper($method);
        $request->headers = array_change_key_case($headers, CASE_LOWER);

        $parsed = parse_url($uri);
        $path = (string) ($parsed['path'] ?? '');

        $path = '/'.trim(preg_replace('#[^A-Za-z0-9_\-/\.]#', '', $path), '/');
        $request->path = $path === '/' ? '/' : rtrim($path, '/');

        $query = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }
        unset($query['path']);
        $request->query = $query;

        $request->body = is_array($body) ? $body : [];

        return $request;
    }

    protected static function readHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        if (function_exists('apache_request_headers')) {
            foreach ((array) apache_request_headers() as $key => $value) {
                $headers[strtolower($key)] = $value;
            }
        }
        return $headers;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHeader(string $name, $default = null)
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * Reads the credential from Authorization: Bearer, or X-Api-Key where a
     * host strips the Authorization header (common on shared hosting).
     */
    public function getBearerToken(): ?string
    {
        $header = (string) $this->getHeader('authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            return trim($matches[1]);
        }

        $alt = $this->getHeader('x-api-key');
        return !empty($alt) ? trim((string) $alt) : null;
    }

    public function query(string $key, $default = null)
    {
        $value = $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public function queryInt(string $key, int $default = 0): int
    {
        return (int) ($this->query[$key] ?? $default);
    }

    public function getQueryAll(): array
    {
        return $this->query;
    }

    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * Requires the given keys to be present and non-empty in the JSON body.
     */
    public function require(array $keys): array
    {
        $missing = [];
        foreach ($keys as $key) {
            if (!isset($this->body[$key]) || $this->body[$key] === '') {
                $missing[] = $key;
            }
        }
        if (!empty($missing)) {
            throw ApiException::unprocessable('Missing required fields.', ['missing' => $missing]);
        }

        return array_intersect_key($this->body, array_flip($keys));
    }

    /**
     * The file being uploaded, however the client chose to send it.
     *
     * Two shapes are accepted because integrators arrive from both worlds: a
     * normal multipart form post (curl -F, browser FormData, Zapier) and a JSON
     * body carrying base64, which is all some low-code platforms can produce.
     *
     * @return array{name: string, bytes: string}|null
     */
    public function getUploadedFile(string $field = 'file'): ?array
    {
        $upload = $_FILES[$field] ?? (is_array($_FILES) ? reset($_FILES) : null);

        if (is_array($upload) && isset($upload['tmp_name']) && !is_array($upload['tmp_name'])) {
            $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                throw ApiException::payloadTooLarge('That file is larger than this server accepts for uploads.');
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw ApiException::badRequest('The upload did not complete.', ['uploadError' => $error]);
            }
            if (!is_uploaded_file($upload['tmp_name'])) {
                throw ApiException::badRequest('The upload could not be read.');
            }

            return [
                'name' => (string) ($upload['name'] ?? 'upload'),
                'bytes' => (string) file_get_contents($upload['tmp_name']),
            ];
        }

        $encoded = $this->input('contentBase64', $this->input('content'));
        if (is_string($encoded) && $encoded !== '') {
            // Data URLs are common when a browser reads a file with FileReader.
            if (preg_match('#^data:[^;]*;base64,(.*)$#s', $encoded, $matches)) {
                $encoded = $matches[1];
            }
            $bytes = base64_decode(strtr(trim($encoded), ' ', '+'), true);
            if ($bytes === false) {
                throw ApiException::unprocessable('contentBase64 is not valid base64 data.');
            }

            return [
                'name' => (string) $this->input('filename', $this->input('name', 'upload')),
                'bytes' => $bytes,
            ];
        }

        return null;
    }

    public function getClientIP(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    public function getUserAgent(): string
    {
        return mb_substr((string) $this->getHeader('user-agent', ''), 0, 255);
    }
}
