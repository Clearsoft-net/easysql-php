<?php

/**
 * EasySQL API Client — with automatic token refresh and token store support.
 *
 * This class wraps the auto-generated API client, providing:
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
 */

namespace Clearsoft\EasySQL\SDK;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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

class EasySQLClient
{
    private Client $httpClient;
    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private ?TokenStoreInterface $tokenStore = null;
    private string $baseUrl;

    private const MAX_RETRIES = 1;

    public function __construct(array $config = [])
    {
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.easysql.net', '/');

        $stack = HandlerStack::create();

        // ── Layer 1: Attach Bearer token to every request ──
        $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            if ($this->accessToken === null) {
                return $request;
            }
            return $request->withHeader('Authorization', "Bearer {$this->accessToken}");
        }), 'attach_token');

        // ── Layer 2: Auto-refresh on 401 ──
        $stack->push(Middleware::retry(function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response,
            ?\Throwable $exception,
        ) {
            // Only retry once and only on 401
            if ($retries >= self::MAX_RETRIES) {
                return false;
            }
            if ($response === null || $response->getStatusCode() !== 401) {
                return false;
            }
            if ($this->refreshToken === null) {
                return false;
            }

            // Attempt token refresh
            try {
                $this->refreshAccessToken();
                return true; // Retry original request with new token
            } catch (\Throwable $e) {
                return false;
            }
        }), 'auto_refresh');

        $this->httpClient = new Client([
            'handler' => $stack,
            'base_uri' => $this->baseUrl,
            'timeout' => $config['timeout'] ?? 30.0,
            'http_errors' => false, // We handle status codes manually
        ]);

        // Load initial tokens from config or store
        if (isset($config['access_token'])) {
            $this->accessToken = $config['access_token'];
            $this->refreshToken = $config['refresh_token'] ?? null;
        }
    }

    /**
     * Register a token store for persistent token management.
     * Tokens will be loaded on login and auto-saved after refresh.
     */
    public function setTokenStore(TokenStoreInterface $store): void
    {
        $this->tokenStore = $store;

        $tokens = $store->load();
        if ($tokens !== null) {
            $this->accessToken = $tokens['access_token'] ?? null;
            $this->refreshToken = $tokens['refresh_token'] ?? null;
        }
    }

    /**
     * Set tokens manually (e.g., after login).
     */
    public function setTokens(string $accessToken, string $refreshToken): void
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;

        if ($this->tokenStore) {
            $this->tokenStore->save($accessToken, $refreshToken);
        }
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
        $this->accessToken = null;
        $this->refreshToken = null;

        if ($this->tokenStore) {
            $this->tokenStore->clear();
        }
    }

    // ── Internal ──────────────────────────────────────────────

    private function refreshAccessToken(): void
    {
        $tempClient = new Client(['base_uri' => $this->baseUrl, 'http_errors' => false]);
        $response = $tempClient->post('/v1/auth/refresh', [
            'json' => ['refresh_token' => $this->refreshToken],
        ]);

        if ($response->getStatusCode() !== 200) {
            $this->clearTokens();
            throw new \RuntimeException('Failed to refresh access token');
        }

        $data = json_decode((string) $response->getBody(), true);
        $this->accessToken = $data['access_token'];
        $this->refreshToken = $data['refresh_token'] ?? $this->refreshToken;

        if ($this->tokenStore) {
            $this->tokenStore->save($this->accessToken, $this->refreshToken);
        }
    }
}
