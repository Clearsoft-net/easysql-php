<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\SQLite;

use RuntimeException;
use Throwable;

/**
 * Connector errors. SQLite has no credentials, so messages are surfaced plainly.
 */
final class ConnectorException extends RuntimeException
{
    public static function connectionFailed(Throwable $previous): self
    {
        return new self(
            'Failed to open SQLite database: ' . $previous->getMessage(),
            0,
            $previous,
        );
    }

    public static function introspectionFailed(Throwable $previous): self
    {
        return new self(
            'SQLite introspection failed: ' . $previous->getMessage(),
            0,
            $previous,
        );
    }

    public static function executionFailed(Throwable $previous): self
    {
        return new self(
            'SQLite query execution failed: ' . $previous->getMessage(),
            0,
            $previous,
        );
    }
}
