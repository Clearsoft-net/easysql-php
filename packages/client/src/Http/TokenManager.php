<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Client\Http;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Holds the current tokens and builds the Guzzle handler stack that
 * injects the Bearer header and auto-refreshes once on a 401.
 *
 * Shared by the generated Client and the EasySQLClient wrapper so token
 * handling lives in exactly one place.
 */
final class TokenManager
{
    private const MAX_RETRIES = 1;

    private ?string $accessToken;
    private ?string $refreshToken;
    private ?TokenStoreInterface $tokenStore = null;
    private string $baseUrl;
    /** @var callable|null */
    private $handler;

    public function __construct(
        string $baseUrl,
        ?string $accessToken = null,
        ?string $refreshToken = null,
        ?callable $handler = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->handler = $handler;
    }

    /**
     * Build the handler stack with token injection + auto-refresh.
     */
    public function buildStack(): HandlerStack
    {
        $stack = HandlerStack::create($this->handler);

        $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            if ($this->accessToken === null) {
                return $request;
            }

            return $request->withHeader('Authorization', "Bearer {$this->accessToken}");
        }), 'attach_token');

        $stack->push(Middleware::retry(function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?\Throwable $exception = null,
        ): bool {
            return $this->shouldRetry($retries, $response);
        }), 'auto_refresh');

        return $stack;
    }

    /**
     * Register a token store for persistent token management.
     * Tokens are loaded immediately and saved after every refresh.
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
        $this->persist();
    }

    /**
     * Set only the access token, keeping any existing refresh token.
     */
    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
        $this->persist();
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    /**
     * Clear stored tokens (e.g., on logout).
     */
    public function clearTokens(): void
    {
        $this->accessToken = null;
        $this->refreshToken = null;

        $this->tokenStore?->clear();
    }

    /**
     * Retry decision for Guzzle's retry middleware: retry once on a 401
     * when a refresh token is available, after refreshing the tokens.
     */
    public function shouldRetry(int $retries, ?ResponseInterface $response): bool
    {
        if ($retries >= self::MAX_RETRIES) {
            return false;
        }
        if ($response === null || $response->getStatusCode() !== 401) {
            return false;
        }
        if ($this->refreshToken === null) {
            return false;
        }

        try {
            $this->refreshAccessToken();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function refreshAccessToken(): void
    {
        $clientConfig = ['base_uri' => $this->baseUrl, 'http_errors' => false];
        if ($this->handler !== null) {
            $clientConfig['handler'] = $this->handler;
        }
        $tempClient = new Client($clientConfig);
        $response = $tempClient->post('/v1/auth/refresh', [
            'json' => ['refresh_token' => $this->refreshToken],
        ]);

        if ($response->getStatusCode() !== 200) {
            $this->clearTokens();
            throw new \RuntimeException('Failed to refresh access token');
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data) || !isset($data['access_token'])) {
            $this->clearTokens();
            throw new \RuntimeException('Malformed refresh response');
        }

        $this->accessToken = (string) $data['access_token'];
        $this->refreshToken = isset($data['refresh_token'])
            ? (string) $data['refresh_token']
            : $this->refreshToken;

        $this->persist();
    }

    private function persist(): void
    {
        if ($this->tokenStore !== null && $this->accessToken !== null && $this->refreshToken !== null) {
            $this->tokenStore->save($this->accessToken, $this->refreshToken);
        }
    }
}
