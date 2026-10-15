<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Snowflake;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;
use InvalidArgumentException;

/**
 * Connection configuration for a Snowflake account.
 *
 * Snowflake is reached through ODBC. Credentials are supplied by the caller
 * per connection and are never written to disk, logged, or kept after the
 * connection closes.
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 443,
        public readonly string $user = '',
        public readonly string $password = '',
        public readonly string $database = '',
        public readonly string $dsn = '',
        public readonly string $driver = 'SnowflakeDSIIDriver',
        public readonly string $account = '',
        public readonly string $schema = 'PUBLIC',
        public readonly string $warehouse = '',
        public readonly string $role = '',
        public readonly float $timeout = 60.0,
    ) {
    }

    /**
     * Builds the config from a `snowflake://` URL. The parsed host is used as
     * both the ODBC server and the account identifier.
     */
    public static function fromUrl(string $url): static
    {
        $parsed = ConnectionUrl::parse($url);
        if ($parsed['type'] !== 'snowflake') {
            throw new InvalidArgumentException(
                "Expected a snowflake connection string, received '{$parsed['type']}'.",
            );
        }

        return new self(
            host: $parsed['host'],
            port: $parsed['port'],
            user: $parsed['user'],
            password: $parsed['password'],
            database: $parsed['database'],
            account: $parsed['host'],
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
