<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\MySQL;

/**
 * Connection configuration for a MySQL/MariaDB database.
 *
 * Credentials are supplied by the caller per connection and are never
 * written to disk, logged, or kept after the connection closes.
 */
final class ConnectionConfig
{
    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 3306,
        public readonly string $user = 'root',
        public readonly string $password = '',
        public readonly string $database = '',
        public readonly string $charset = 'utf8mb4',
        public readonly bool $ssl = false,
        public readonly float $timeout = 10.0,
    ) {
    }

    /**
     * Build a PDO DSN for this configuration.
     */
    public function toDsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset,
        );
    }
}
