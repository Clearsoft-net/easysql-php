<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Oracle;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;
use InvalidArgumentException;

/**
 * Connection configuration for an Oracle Database server.
 *
 * The `database` field holds the Oracle service name (or SID); it is the `/%s`
 * segment of the Easy Connect string `//host:port/service`. Credentials are
 * supplied by the caller per connection and are never written to disk, logged,
 * or kept after the connection closes.
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 1521,
        public readonly string $user = 'system',
        public readonly string $password = '',
        public readonly string $database = '',
        public readonly float $timeout = 10.0,
    ) {
    }

    /**
     * Builds the config from an `oracle://` URL.
     */
    public static function fromUrl(string $url): static
    {
        $parsed = ConnectionUrl::parse($url);
        if (!in_array($parsed['type'], ['oracle'], true)) {
            throw new InvalidArgumentException(
                "Expected an oracle connection string, received '{$parsed['type']}'.",
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
            'oci:dbname=//%s:%d/%s',
            $this->host,
            $this->port,
            $this->database,
        );
    }
}
