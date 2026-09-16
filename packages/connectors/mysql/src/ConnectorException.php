<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\MySQL;

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
            'Failed to connect to MySQL: ' . CredentialSanitizer::sanitize($previous->getMessage()),
            0,
            $previous,
        );
    }

    public static function introspectionFailed(Throwable $previous): self
    {
        return new self(
            'MySQL introspection failed: ' . CredentialSanitizer::sanitize($previous->getMessage()),
            0,
            $previous,
        );
    }

    public static function executionFailed(Throwable $previous): self
    {
        return new self(
            'MySQL query execution failed: ' . CredentialSanitizer::sanitize($previous->getMessage()),
            0,
            $previous,
        );
    }
}
