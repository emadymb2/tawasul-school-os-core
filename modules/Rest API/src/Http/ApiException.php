<?php
namespace Gibbon\Module\RestAPI\Http;

/**
 * Every failure inside the API is expressed as an ApiException, so api.php can
 * turn it into one consistent JSON error envelope and one log line.
 */
class ApiException extends \Exception
{
    protected $statusCode;
    protected $errorCode;
    protected $details;

    public function __construct(int $statusCode, string $errorCode, string $message, array $details = [])
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public static function badRequest(string $message, array $details = []): self
    {
        return new self(400, 'bad_request', $message, $details);
    }

    public static function unauthorized(string $message): self
    {
        return new self(401, 'unauthorized', $message);
    }

    public static function forbidden(string $message, array $details = []): self
    {
        return new self(403, 'forbidden', $message, $details);
    }

    public static function notFound(string $message, array $details = []): self
    {
        return new self(404, 'not_found', $message, $details);
    }

    public static function conflict(string $message, array $details = []): self
    {
        return new self(409, 'conflict', $message, $details);
    }

    public static function unprocessable(string $message, array $details = []): self
    {
        return new self(422, 'unprocessable_entity', $message, $details);
    }

    public static function rateLimited(string $message, array $details = []): self
    {
        return new self(429, 'rate_limited', $message, $details);
    }

    public static function payloadTooLarge(string $message, array $details = []): self
    {
        return new self(413, 'payload_too_large', $message, $details);
    }

    public static function unavailable(string $message): self
    {
        return new self(503, 'service_unavailable', $message);
    }

    /**
     * Returns the standard API error envelope for this exception.
     */
    public function toResponse(): array
    {
        return ['error' => array_filter([
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'details' => $this->details,
        ])];
    }

    /**
     * Returns the HTTP headers (if any) attached to this exception.
     */
    public function getHeaders(): array
    {
        return [];
    }
}
