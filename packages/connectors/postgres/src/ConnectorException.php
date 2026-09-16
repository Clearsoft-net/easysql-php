<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Postgres;

use Clearsoft\EasySQL\Common\CredentialSanitizer;
use RuntimeException;
use Throwable;

/**
 * Connector errors with credentials stripped from driver messages.
 */
final class ConnectorException extends RuntimeException
{
    public static function connectionFailed(Throwable $previous): self
    {
        return new self(
            'Failed to connect to PostgreSQL: ' . CredentialSanitizer::sanitize($previous->getMessage()),
            0,
            $previous,
        );
    }

    public static function introspectionFailed(Throwable $previous): self
    {
        return new self(
            'PostgreSQL introspection failed: ' . CredentialSanitizer::sanitize($previous->getMessage()),
            0,
            $previous,
        );
    }

    public static function executionFailed(Throwable $previous): self
    {
        return new self(
            'PostgreSQL query execution failed: ' . CredentialSanitizer::sanitize($previous->getMessage()),
            0,
            $previous,
        );
    }
}
