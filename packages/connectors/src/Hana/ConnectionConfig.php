<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Hana;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;
use InvalidArgumentException;

/**
 * Connection configuration for an SAP HANA server.
 *
 * HANA is reached through ODBC. Credentials are supplied by the caller per
 * connection and are never written to disk, logged, or kept after the
 * connection closes.
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 30015,
        public readonly string $user = '',
        public readonly string $password = '',
        public readonly string $database = '',
        public readonly string $dsn = '',
        public readonly string $driver = 'HDBODBC',
        public readonly string $schema = '',
        public readonly bool $ssl = false,
        public readonly float $timeout = 10.0,
    ) {
    }

    /**
     * Builds the config from a `hana://` URL.
     */
    public static function fromUrl(string $url): static
    {
        $parsed = ConnectionUrl::parse($url);
        if ($parsed['type'] !== 'hana') {
            throw new InvalidArgumentException(
                "Expected a hana connection string, received '{$parsed['type']}'.",
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
        if ($this->dsn !== '') {
            return 'odbc:' . $this->dsn;
        }

        return 'odbc:DRIVER={' . $this->driver . '};SERVER=' . $this->host
            . ';PORT=' . $this->port . ';DATABASE=' . $this->database
            . ';UID=' . $this->user . ';PWD=' . $this->password;
    }
}
