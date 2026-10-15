<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\ClickHouse;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;
use InvalidArgumentException;

/**
 * Connection configuration for a ClickHouse server (HTTP interface).
 *
 * Credentials are supplied by the caller per connection and are never
 * written to disk, logged, or kept after the connection closes.
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    public readonly int $port;

    public function __construct(
        public readonly string $host = '127.0.0.1',
        ?int $port = null,
        public readonly string $user = 'default',
        public readonly string $password = '',
        public readonly string $database = 'default',
        public readonly bool $ssl = false,
        public readonly float $timeout = 10.0,
    ) {
        // Default HTTP interface port: 8443 over TLS, 8123 otherwise.
        $this->port = $port ?? ($ssl ? 8443 : 8123);
    }

    /**
     * Builds the config from a `clickhouse://` (8123) or `clickhouses://`
     * (8443, TLS) URL.
     */
    public static function fromUrl(string $url): static
    {
        $parsed = ConnectionUrl::parse($url);
        if ($parsed['type'] !== 'clickhouse') {
            throw new InvalidArgumentException(
                "Expected a clickhouse connection string, received '{$parsed['type']}'.",
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
     * Base URL of the ClickHouse HTTP interface (no trailing slash).
     */
    public function baseUrl(): string
    {
        return sprintf('%s://%s:%d', $this->ssl ? 'https' : 'http', $this->host, $this->port);
    }
}
