<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\SqlServer;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;
use InvalidArgumentException;

/**
 * Connection configuration for a Microsoft SQL Server instance.
 *
 * Credentials are supplied by the caller per connection and are never
 * written to disk, logged, or kept after the connection closes.
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 1433,
        public readonly string $user = 'sa',
        public readonly string $password = '',
        public readonly string $database = '',
        public readonly bool $encrypt = true,
        public readonly bool $trustServerCertificate = false,
        public readonly float $timeout = 10.0,
    ) {
    }

    /**
     * Builds the config from a `sqlserver://` / `mssql://` URL.
     */
    public static function fromUrl(string $url): static
    {
        $parsed = ConnectionUrl::parse($url);
        if (!in_array($parsed['type'], ['sqlserver'], true)) {
            throw new InvalidArgumentException(
                "Expected a sqlserver connection string, received '{$parsed['type']}'.",
            );
        }

        return new self(
            host: $parsed['host'],
            port: $parsed['port'],
            user: $parsed['user'],
            password: $parsed['password'],
            database: $parsed['database'],
        );
    }

    /**
     * Build a PDO DSN for this configuration.
     */
    public function toDsn(): string
    {
        return sprintf(
            'sqlsrv:Server=%s,%d;Database=%s',
            $this->host,
            $this->port,
            $this->database,
        );
    }
}
