<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Client\Http;

use GuzzleHttp\Client;

/**
 * EasySQL API client with automatic token refresh and token store support.
 *
 * Thin wrapper around a Guzzle client with token management delegated to
 * {@see TokenManager}, providing:
 *  - Automatic Bearer token injection
 *  - Automatic token refresh on 401 responses
 *  - Optional TokenStoreInterface for persistent token storage
 *
 * Usage:
 *   $client = new EasySQLClient(['access_token' => '...']);
 *   $client->setTokenStore(new MyTokenStore());
 *
 *   // All API calls go through $client->getHttpClient()
 *   $response = $client->getHttpClient()->post('/v1/auth/login', [...]);
 *
 * For the typed endpoint methods use {@see \Clearsoft\EasySQL\Client\Client},
 * which supports the same token store and auto-refresh.
 */
class EasySQLClient
{
    private Client $httpClient;
    private TokenManager $tokens;

    public function __construct(array $config = [])
    {
        $baseUrl = rtrim($config['base_url'] ?? 'https://api.easysql.net', '/');

        $this->tokens = new TokenManager(
            $baseUrl,
            $config['access_token'] ?? null,
            $config['refresh_token'] ?? null,
            $config['handler'] ?? null,
        );

        $this->httpClient = new Client([
            'handler' => $this->tokens->buildStack(),
            'base_uri' => $baseUrl,
            'timeout' => $config['timeout'] ?? 30.0,
            'http_errors' => false, // We handle status codes manually
        ]);
    }

    /**
     * Register a token store for persistent token management.
     * Tokens will be loaded on login and auto-saved after refresh.
     */
    public function setTokenStore(TokenStoreInterface $store): void
    {
        $this->tokens->setTokenStore($store);
    }

    /**
     * Set tokens manually (e.g., after login).
     */
    public function setTokens(string $accessToken, string $refreshToken): void
    {
        $this->tokens->setTokens($accessToken, $refreshToken);
    }

    /**
     * Get the underlying Guzzle client for making API calls.
     */
    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }

    /**
     * Clear stored tokens (e.g., on logout).
     */
    public function clearTokens(): void
    {
        $this->tokens->clearTokens();
    }
}
