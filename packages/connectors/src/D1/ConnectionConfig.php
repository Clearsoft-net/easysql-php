<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\D1;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;
use InvalidArgumentException;

/**
 * Connection configuration for a Cloudflare D1 database.
 *
 * D1 has no PHP driver: the database is reached over Cloudflare's REST API
 * (`/accounts/{account_id}/d1/database/{database_id}/query`), so a connection
 * is nothing more than an account/database pair plus an API token. The token is
 * held in memory for the lifetime of the connection only and is stripped from
 * every error message that leaves the process.
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    /** Default Cloudflare API root, including the version prefix. */
    public const DEFAULT_BASE_URL = 'https://api.cloudflare.com/client/v4';

    public function __construct(
        public readonly string $accountId = '',
        public readonly string $databaseId = '',
        public readonly string $apiToken = '',
        public readonly string $baseUrl = self::DEFAULT_BASE_URL,
        public readonly float $timeout = 10.0,
    ) {
    }

    /**
     * Builds the config from a `d1://{accountId}/{databaseId}` URL. The API
     * token is read from the `api_token` (or `apiToken`/`token`) query parameter
     * or, failing that, the password position of the userinfo.
     */
    public static function fromUrl(string $url): static
    {
        $parsed = ConnectionUrl::parse($url);
        if ($parsed['type'] !== 'd1') {
            throw new InvalidArgumentException(
                "Expected a d1 connection string, received '{$parsed['type']}'.",
            );
        }

        $parts = parse_url($url);
        $query = [];
        if (is_array($parts) && isset($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        $apiToken = (string) ($query['api_token'] ?? $query['apiToken'] ?? $query['token'] ?? '');
        if ($apiToken === '') {
            $apiToken = $parsed['password'];
        }

        return new self(
            accountId: $parsed['host'],
            databaseId: $parsed['database'],
            apiToken: $apiToken,
        );
    }

    /**
     * Base URL of the Cloudflare API (no trailing slash).
     */
    public function baseUrlTrimmed(): string
    {
        return rtrim($this->baseUrl, '/');
    }
}
