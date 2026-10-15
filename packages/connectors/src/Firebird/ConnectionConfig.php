<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Firebird;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;
use InvalidArgumentException;

/**
 * Connection configuration for a Firebird database (remote or embedded).
 *
 * `database` holds the database path or alias; for a remote server it is the
 * `:path` segment of `host/port:path`. Credentials are supplied by the caller
 * per connection and are never written to disk, logged, or kept after the
 * connection closes.
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    public function __construct(
        public readonly string $host = 'localhost',
        public readonly int $port = 3050,
        public readonly string $user = 'sysdba',
        public readonly string $password = '',
        public readonly string $database = '',
        public readonly string $role = '',
        public readonly float $timeout = 10.0,
    ) {
    }

    /**
     * Builds the config from a `firebird://` URL.
     */
    public static function fromUrl(string $url): static
    {
        $parsed = ConnectionUrl::parse($url);
        if (!in_array($parsed['type'], ['firebird'], true)) {
            throw new InvalidArgumentException(
                "Expected a firebird connection string, received '{$parsed['type']}'.",
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
            'firebird:dbname=%s/%d:%s',
            $this->host,
            $this->port,
            $this->database,
        );
    }
}
