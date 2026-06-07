<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\SDK\Exceptions;

use Psr\Http\Message\ResponseInterface;

class ApiException extends \RuntimeException
{
    private int $statusCode;
    private array $errorDetails = [];

    public function __construct(string $message, int $statusCode, array $errorDetails = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->errorDetails = $errorDetails;
    }

    /**
     * Create an ApiException from a PSR-7 Response.
     */
    public static function fromResponse(ResponseInterface $response): self
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        $details = [];

        if (!empty($body)) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $details = $decoded;
            }
        }

        // Derive user-friendly message
        $message = $details['message'] ?? $details['detail'] ?? $response->getReasonPhrase() ?: 'An error occurred';
        if (is_array($message)) {
            $message = json_encode($message);
        }

        return new self($message, $statusCode, $details);
    }

    /**
     * Get the HTTP status code returned by the server.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the decoded JSON error details returned by the server.
     */
    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }
}
