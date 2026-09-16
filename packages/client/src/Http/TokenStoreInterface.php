<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Client\Http;

/**
 * Store interface for persisting access/refresh tokens between requests.
 * Implement this to save tokens in session, database, cache, etc.
 */
interface TokenStoreInterface
{
    public function load(): ?array;       // Returns ['access_token' => ..., 'refresh_token' => ...] or null
    public function save(string $accessToken, string $refreshToken): void;
    public function clear(): void;
}
