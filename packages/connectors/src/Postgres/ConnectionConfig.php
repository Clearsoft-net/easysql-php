<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Postgres;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;
use InvalidArgumentException;

/**
 * Connection configuration for a PostgreSQL database.
 *
 * Credentials are supplied by the caller per connection and are never
 * written to disk, logged, or kept after the connection closes.
 *
 * The same config serves every PostgreSQL-compatible server. `provider`
 * selects which catalog queries are safe (Redshift is PostgreSQL 8.0 based)
 * and `transport` selects how the server is reached: the wire protocol over
 * TCP (PDO), or Neon's HTTP SQL endpoint.
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    /** PostgreSQL-compatible providers (the engine id stays `postgresql`). */
    public const PROVIDER_POSTGRES = 'postgres';
    public const PROVIDER_NEON = 'neon';
    public const PROVIDER_COCKROACHDB = 'cockroachdb';
    public const PROVIDER_REDSHIFT = 'redshift';

    /** How the server is reached. */
    public const TRANSPORT_TCP = 'tcp';
    public const TRANSPORT_HTTP = 'http';
    public const TRANSPORT_WEBSOCKET = 'websocket';

    /**
     * @param list<string> $schemas Schemas to introspect (default: public)
     * @param string|null $connectionString A full `postgresql://` string; when
     *   set it takes precedence over host/port/user/password/database. Required
     *   for the `http`/`websocket` transports.
     * @param string|null $endpoint Override for the Neon HTTP endpoint
     *   (defaults to `https://api.<host>/sql`, derived from the connection string).
     */
    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 5432,
        public readonly string $user = 'postgres',
        public readonly string $password = '',
        public readonly string $database = 'postgres',
        public readonly string $sslmode = 'prefer',
        public readonly array $schemas = ['public'],
        public readonly float $timeout = 10.0,
        public readonly ?string $connectionString = null,
        public readonly string $provider = self::PROVIDER_POSTGRES,
        public readonly string $transport = self::TRANSPORT_TCP,
        public readonly ?string $socket = null,
        public readonly ?string $endpoint = null,
    ) {
    }

    /**
     * Builds the config from a `postgresql://` / `postgres://` URL. The parsed
     * `ssl` flag maps onto libpq's `sslmode` (`require` vs `prefer`).
     */
    public static function fromUrl(string $url): static
    {
        $parsed = ConnectionUrl::parse($url);
        if ($parsed['type'] !== 'postgresql') {
            throw new InvalidArgumentException(
                "Expected a postgresql connection string, received '{$parsed['type']}'.",
            );
        }

        return new self(
            host: $parsed['host'],
            port: $parsed['port'],
            user: $parsed['user'],
            password: $parsed['password'],
            database: $parsed['database'],
            sslmode: $parsed['ssl'] ? 'require' : 'prefer',
        );
    }

    /**
     * Build a PDO DSN for this configuration.
     */
    public function toDsn(): string
    {
        return sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->sslmode,
        );
    }
}
