<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Trino;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;
use InvalidArgumentException;

/**
 * Connection configuration for a Trino coordinator (REST interface).
 *
 * Trino has no dedicated wire driver in PHP: every statement is an HTTP call
 * against the coordinator's `/v1/statement` endpoint, so the configuration is
 * just the coordinator address plus an optional catalog/schema and HTTP Basic
 * credentials. Credentials are supplied by the caller per connection and are
 * never written to disk, logged, or kept after the connection closes.
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    public readonly int $port;

    public function __construct(
        public readonly string $host = '127.0.0.1',
        ?int $port = null,
        public readonly string $user = 'admin',
        public readonly string $password = '',
        public readonly bool $ssl = false,
        public readonly string $catalog = '',
        public readonly string $schema = '',
        public readonly float $timeout = 10.0,
    ) {
        // Conventional Trino coordinator port.
        $this->port = $port ?? 8080;
    }

    /**
     * Builds the config from a `trino://user[:pass]@host[:port]/catalog[/schema]`
     * URL. The path carries the default catalog (and, when present, schema).
     */
    public static function fromUrl(string $url): static
    {
        $parsed = ConnectionUrl::parse($url);
        if ($parsed['type'] !== 'trino') {
            throw new InvalidArgumentException(
                "Expected a trino connection string, received '{$parsed['type']}'.",
            );
        }

        $catalog = $parsed['database'];
        $schema = '';
        if (str_contains($catalog, '/')) {
            [$catalog, $schema] = explode('/', $catalog, 2);
        }

        return new self(
            host: $parsed['host'],
            port: $parsed['port'],
            user: $parsed['user'],
            password: $parsed['password'],
            ssl: $parsed['ssl'],
            catalog: $catalog,
            schema: $schema,
        );
    }

    /**
     * Base URL of the coordinator (no trailing slash). A full `http(s)://host`
     * given as `host` is preserved, defaulting its port when omitted.
     */
    public function baseUrl(): string
    {
        $host = trim($this->host);

        if (preg_match('#^https?://#i', $host) === 1) {
            $parsed = parse_url($host);
            if ($parsed === false) {
                throw new InvalidArgumentException("Invalid Trino host URL: '{$this->host}'.");
            }

            return sprintf(
                '%s://%s:%d',
                $parsed['scheme'] ?? ($this->ssl ? 'https' : 'http'),
                $parsed['host'] ?? $host,
                $parsed['port'] ?? $this->port,
            );
        }

        return sprintf('%s://%s:%d', $this->ssl ? 'https' : 'http', $host, $this->port);
    }
}
