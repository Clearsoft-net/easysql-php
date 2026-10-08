<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\ClickHouse;

/**
 * Connection configuration for a ClickHouse server (HTTP interface).
 *
 * Credentials are supplied by the caller per connection and are never
 * written to disk, logged, or kept after the connection closes.
 */
final class ConnectionConfig
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
     * Base URL of the ClickHouse HTTP interface (no trailing slash).
     */
    public function baseUrl(): string
    {
        return sprintf('%s://%s:%d', $this->ssl ? 'https' : 'http', $this->host, $this->port);
    }
}
