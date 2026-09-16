<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\SQLite;

/**
 * Connection configuration for a SQLite database.
 *
 * SQLite lives on the same machine as the client — the file path is local
 * metadata and is never sent to the API (only the schema payload is).
 */
final class ConnectionConfig
{
    public function __construct(
        public readonly string $path = ':memory:',
        public readonly bool $readOnly = false,
        public readonly float $timeout = 10.0,
    ) {
    }

    /**
     * Build a PDO DSN for this configuration.
     */
    public function toDsn(): string
    {
        return 'sqlite:' . $this->path;
    }
}
