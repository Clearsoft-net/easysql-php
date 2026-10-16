<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\MySQL;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;
use InvalidArgumentException;

/**
 * Connection configuration for a MySQL/MariaDB database.
 *
 * Credentials are supplied by the caller per connection and are never
 * written to disk, logged, or kept after the connection closes.
 *
 * A connection is opened either over TCP (`host`/`port`) or over a local Unix
 * socket. When `socket` is set it takes precedence and host/port are ignored,
 * mirroring the mutual exclusivity of the TypeScript sibling package.
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    /**
     * @param string|null $socket Local Unix socket path (e.g.
     *   `/var/run/mysqld/mysqld.sock`). When set, the connection uses the
     *   socket and ignores host/port; leave null to connect over TCP.
     */
    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 3306,
        public readonly ?string $socket = null,
        public readonly string $user = 'root',
        public readonly string $password = '',
        public readonly string $database = '',
        public readonly string $charset = 'utf8mb4',
        public readonly bool $ssl = false,
        public readonly float $timeout = 10.0,
        public readonly string $engine = 'mysql',
    ) {
    }

    /**
     * Builds the config from a `mysql://` / `mariadb://` URL.
     *
     * URLs express a TCP target only — a Unix socket has no representation in
     * this form, so pass the `socket` constructor argument instead.
     */
    public static function fromUrl(string $url): static
    {
        $parsed = ConnectionUrl::parse($url);
        if (!in_array($parsed['type'], ['mysql', 'mariadb'], true)) {
            throw new InvalidArgumentException(
                "Expected a mysql/mariadb connection string, received '{$parsed['type']}'.",
            );
        }

        return new self(
            host: $parsed['host'],
            port: $parsed['port'],
            user: $parsed['user'],
            password: $parsed['password'],
            database: $parsed['database'],
            ssl: $parsed['ssl'],
            engine: $parsed['type'],
        );
    }

    /**
     * Build a PDO DSN for this configuration.
     *
     * With a Unix socket the DSN carries `unix_socket` and omits host/port;
     * otherwise it is the conventional TCP DSN.
     */
    public function toDsn(): string
    {
        if ($this->socket !== null) {
            return sprintf(
                'mysql:unix_socket=%s;dbname=%s;charset=%s',
                $this->socket,
                $this->database,
                $this->charset,
            );
        }

        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset,
        );
    }
}
