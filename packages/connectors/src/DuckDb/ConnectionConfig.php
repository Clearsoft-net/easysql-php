<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\DuckDb;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;
use InvalidArgumentException;

/**
 * Connection configuration for a local DuckDB database.
 *
 * DuckDB lives on the same machine as the client — the file path is local
 * metadata and is never sent to the API (only the schema payload is).
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    public function __construct(
        public readonly string $file = ':memory:',
        public readonly bool $readOnly = false,
        public readonly float $timeout = 10.0,
    ) {
    }

    /**
     * Builds the config from a bare file path, `:memory:`, or a
     * `duckdb:///path/to.db` URL.
     */
    public static function fromUrl(string $url): static
    {
        if (!str_starts_with($url, 'duckdb:')) {
            return new self(file: $url);
        }

        $parsed = ConnectionUrl::parse($url);
        if ($parsed['type'] !== 'duckdb') {
            throw new InvalidArgumentException(
                "Expected a duckdb connection string, received '{$parsed['type']}'.",
            );
        }

        return new self(file: $parsed['database']);
    }

    /**
     * Build a PDO DSN for this configuration.
     */
    public function toDsn(): string
    {
        return 'duckdb:' . $this->file;
    }
}
