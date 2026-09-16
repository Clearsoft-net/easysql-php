<?php

declare(strict_types=1);

/**
 * Sample 02 — Persistent tokens with automatic refresh.
 *
 * The typed Client refreshes the access token once on a 401 and persists the
 * new pair through the TokenStoreInterface. Swap the store for Redis, the
 * database or the filesystem in production.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=... EASYSQL_REFRESH_TOKEN=... php samples/02-token-store-refresh.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;
use Clearsoft\EasySQL\Client\Http\TokenStoreInterface;

final class FileTokenStore implements TokenStoreInterface
{
    public function __construct(private readonly string $file)
    {
    }

    public function load(): ?array
    {
        if (!is_file($this->file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->file), true);

        return is_array($data) ? $data : null;
    }

    public function save(string $accessToken, string $refreshToken): void
    {
        file_put_contents($this->file, json_encode([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ]));
    }

    public function clear(): void
    {
        @unlink($this->file);
    }
}

$tokens = sys_get_temp_dir() . '/easysql-tokens.json';
@unlink($tokens);
file_put_contents($tokens, json_encode([
    'access_token' => getenv('EASYSQL_ACCESS_TOKEN') ?: '',
    'refresh_token' => getenv('EASYSQL_REFRESH_TOKEN') ?: '',
]));

$client = new Client(['base_url' => getenv('EASYSQL_BASE_URL') ?: 'https://api.easysql.net']);
$client->setTokenStore(new FileTokenStore($tokens));

// This call transparently refreshes the token if the access token expired.
$user = $client->me();
echo "Signed in as {$user['email']}" . PHP_EOL;

// Logout clears the persisted tokens.
$client->clearTokens();
echo "Tokens cleared." . PHP_EOL;
