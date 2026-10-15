<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Db2;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;
use InvalidArgumentException;

/**
 * Connection configuration for an IBM Db2 server.
 *
 * Credentials are supplied by the caller per connection and are never written to
 * disk, logged, or kept after the connection closes.
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    public function __construct(
        public readonly string $host = 'localhost',
        public readonly int $port = 50000,
        public readonly string $user = 'db2inst1',
        public readonly string $password = '',
        public readonly string $database = '',
        public readonly bool $ssl = false,
        public readonly float $timeout = 10.0,
    ) {
    }

    /**
     * Builds the config from a `db2://` URL.
     */
    public static function fromUrl(string $url): static
    {
        $parsed = ConnectionUrl::parse($url);
        if (!in_array($parsed['type'], ['db2'], true)) {
            throw new InvalidArgumentException(
                "Expected a db2 connection string, received '{$parsed['type']}'.",
            );
        }

        return new self(
            host: $parsed['host'],
            port: $parsed['port'],
            user: $parsed['user'],
            password: $parsed['password'],
            database: $parsed['database'],
            ssl: $parsed['ssl'],
        );
    }

    /**
     * Build a PDO DSN for this configuration.
     */
    public function toDsn(): string
    {
        return sprintf(
            'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=%s;HOSTNAME=%s;PORT=%d;'
            . 'PROTOCOL=TCPIP;UID=%s;PWD=%s',
            $this->database,
            $this->host,
            $this->port,
            $this->user,
            $this->password,
        );
    }
}
