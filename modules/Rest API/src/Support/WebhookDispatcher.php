<?php
namespace Gibbon\Module\RestAPI\Support;

use PDO;
use Gibbon\Module\RestAPI\Domain\WebhookGateway;

/**
 * Publishes change events to subscribed URLs.
 *
 * Events are queued while the request is handled and sent once, after the
 * database work has succeeded, so a receiver is never told about a write that
 * was rolled back. Each delivery carries an HMAC-SHA256 signature over the
 * exact body, in the same shape Stripe and GitHub use, so a receiver can
 * verify the call really came from this Gibbon:
 *
 *   X-Gibbon-Event      students.created
 *   X-Gibbon-Delivery   a unique id for this attempt
 *   X-Gibbon-Timestamp  unix seconds
 *   X-Gibbon-Signature  sha256=<hmac of "<timestamp>.<body>" with the secret>
 *
 * Delivery is best-effort with a short timeout: an unreachable endpoint slows
 * nobody down and is recorded as a failed delivery for the administrator.
 */
class WebhookDispatcher
{
    protected $pdo;
    protected $settings;
    protected $gateway;
    protected $queue = [];

    public function __construct(PDO $pdo, Settings $settings)
    {
        $this->pdo = $pdo;
        $this->settings = $settings;
        $this->gateway = new WebhookGateway($pdo);
    }

    public function queue(string $event, array $payload): void
    {
        if (!$this->settings->isOn('webhooksEnabled', false)) {
            return;
        }

        $this->queue[] = ['event' => $event, 'payload' => $payload];
    }

    public function hasQueued(): bool
    {
        return !empty($this->queue);
    }

    /**
     * @return array<int, array> one summary row per delivery attempt
     */
    public function flush(): array
    {
        if (empty($this->queue) || !$this->settings->isOn('webhooksEnabled', false)) {
            $this->queue = [];
            return [];
        }

        $timeout = max(1, $this->settings->getInt('webhookTimeout', 5));
        $results = [];

        foreach ($this->queue as $item) {
            foreach ($this->gateway->selectForEvent($item['event']) as $subscription) {
                $results[] = $this->send($subscription, $item['event'], $item['payload'], $timeout);
            }
        }

        $this->queue = [];

        return $results;
    }

    protected function send(array $subscription, string $event, array $payload, int $timeout): array
    {
        $url = (string) $subscription['url'];

        if (!$this->isAllowedURL($url)) {
            $this->gateway->recordDelivery([
                'restApiWebhookID' => $subscription['restApiWebhookID'],
                'event' => $event,
                'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'statusCode' => 0,
                'success' => false,
                'durationMS' => 0,
                'response' => 'Webhook URL was rejected: must be http(s) and must not resolve to a private or loopback address.',
            ]);

            return [
                'webhook' => $subscription['name'],
                'event' => $event,
                'statusCode' => 0,
                'success' => false,
            ];
        }

        $deliveryID = bin2hex(random_bytes(8));
        $timestamp = time();
        $body = json_encode([
            'id' => $deliveryID,
            'event' => $event,
            'timestamp' => date('c', $timestamp),
            'data' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) $subscription['secret']);

        $headers = [
            'Content-Type: application/json',
            'User-Agent: Gibbon-REST-API-Webhook',
            'X-Gibbon-Event: '.$event,
            'X-Gibbon-Delivery: '.$deliveryID,
            'X-Gibbon-Timestamp: '.$timestamp,
            'X-Gibbon-Signature: sha256='.$signature,
        ];

        foreach (array_filter(explode("\n", (string) ($subscription['headers'] ?? ''))) as $extra) {
            $extra = trim($extra);
            if ($extra !== '' && strpos($extra, ':') !== false) {
                $headers[] = $extra;
            }
        }

        $startedAt = microtime(true);
        $status = 0;
        $response = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $response = $raw === false ? curl_error($ch) : (string) $raw;
            curl_close($ch);
        } else {
            $context = stream_context_create(['http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ]]);
            $raw = @file_get_contents($url, false, $context);
            $response = $raw === false ? 'Request failed' : (string) $raw;
            foreach ($http_response_header ?? [] as $line) {
                if (preg_match('#HTTP/\S+\s+(\d{3})#', $line, $m)) {
                    $status = (int) $m[1];
                    break;
                }
            }
        }

        $success = $status >= 200 && $status < 300;

        $this->gateway->recordDelivery([
            'restApiWebhookID' => $subscription['restApiWebhookID'],
            'event' => $event,
            'payload' => $body,
            'statusCode' => $status,
            'success' => $success,
            'durationMS' => (int) round((microtime(true) - $startedAt) * 1000),
            'response' => $response,
        ]);

        return [
            'webhook' => $subscription['name'],
            'event' => $event,
            'statusCode' => $status,
            'success' => $success,
        ];
    }

    /**
     * Guards against SSRF: only http(s) URLs whose hostname resolves to a public
     * address are dispatched. localhost, private ranges and link-local addresses
     * are rejected so a misconfigured or malicious webhook cannot be used to read
     * internal services.
     */
    protected function isAllowedURL(string $url): bool
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return false;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));
        if ($host === '' || $host === 'localhost') {
            return false;
        }

        // Resolve hostname to IPv4; if it is already an IP literal gethostbyname
        // returns it unchanged.
        $ip = gethostbyname($host);
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Reject private (10.x, 172.16-31.x, 192.168.x, fc00::/7) and reserved
        // (127.x, 169.254.x, ::1, ::, etc.) ranges for both IPv4 and IPv6.
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RESV_RANGE
        );
    }
}
