<?php
namespace Gibbon\Module\RestAPI\Http;

use Gibbon\Module\RestAPI\Support\Settings;

/**
 * Buffers the JSON response so the request can still be logged before output.
 */
class Response
{
    protected $settings;
    protected $payload = null;
    protected $status = 200;
    protected $extraHeaders = [];

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function sendCorsHeaders(Request $request): void
    {
        $origins = trim((string) $this->settings->get('corsOrigins', ''));
        if ($origins === '') {
            return;
        }


        $origin = (string) $request->getHeader('origin', '');
        $allowed = array_map('trim', explode(',', $origins));

        if ($origins === '*') {
            header('Access-Control-Allow-Origin: *');
        } elseif ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: '.$origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Api-Key, X-Http-Method-Override, X-School-Year');
        header('Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining');
        header('Access-Control-Max-Age: 600');
    }

    public function withHeader(string $name, string $value): void
    {
        $this->extraHeaders[$name] = $value;
    }

    public function json($body, int $status = 200, bool $flush = true): void
    {
        $this->payload = $body;
        $this->status = $status;
        if ($flush) {
            $this->flush();
        }
    }

    /**
     * Streams a stored file instead of a JSON body.
     *
     * Content-Disposition defaults to attachment: a download endpoint must
     * never let a stored document render in the browser as part of the Gibbon
     * origin. Pass ?disposition=inline to preview an image on purpose.
     */
    public function sendFile(array $file, bool $inline = false): void
    {
        http_response_code(200);
        header('Content-Type: '.$file['mime']);
        header('Content-Length: '.$file['size']);
        header('Content-Disposition: '.($inline ? 'inline' : 'attachment').'; filename="'.str_replace('"', '', $file['name']).'"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        header('Cache-Control: private, max-age=0, must-revalidate');
        foreach ($this->extraHeaders as $name => $value) {
            header($name.': '.$value);
        }

        // readfile() streams in chunks, so a 200 MB report export does not have
        // to fit in the PHP memory limit.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($file['path']);
        exit;
    }

    public function noContent(): void
    {
        http_response_code(204);
        exit;
    }

    /**
     * Streams a CSV export. Used by the export endpoint so that large result
     * sets are sent to the browser without buffering the whole file in memory.
     */
    public function csv(string $filename, \Closure $writer): void
    {
        http_response_code(200);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.str_replace('"', '', $filename).'"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');
        foreach ($this->extraHeaders as $name => $value) {
            header($name.': '.$value);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $handle = fopen('php://output', 'w');
        // UTF-8 BOM so Excel opens Arabic/encoding correctly.
        fwrite($handle, "\xEF\xBB\xBF");
        $writer($handle);
        fclose($handle);
        exit;
    }

    public function flush(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        foreach ($this->extraHeaders as $name => $value) {
            header($name.': '.$value);
        }

        if ($this->status !== 204) {
            echo json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        exit;
    }
}
