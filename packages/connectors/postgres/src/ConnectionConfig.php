<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Postgres;

/**
 * Connection configuration for a PostgreSQL database.
 *
 * Credentials are supplied by the caller per connection and are never
 * written to disk, logged, or kept after the connection closes.
 */
final class ConnectionConfig
{
    /** @param list<string> $schemas Schemas to introspect (default: public) */
    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 5432,
        public readonly string $user = 'postgres',
        public readonly string $password = '',
        public readonly string $database = 'postgres',
        public readonly string $sslmode = 'prefer',
        public readonly array $schemas = ['public'],
        public readonly float $timeout = 10.0,
    ) {
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
