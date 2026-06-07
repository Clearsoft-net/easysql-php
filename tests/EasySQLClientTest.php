<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\SDK\Tests;

use Clearsoft\EasySQL\SDK\EasySQLClient;
use Clearsoft\EasySQL\SDK\TokenStoreInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class EasySQLClientTest extends TestCase
{
    private function createMockStore(?array $initialTokens = null): TokenStoreInterface
    {
        return new class($initialTokens) implements TokenStoreInterface {
            private ?array $tokens;

            public function __construct(?array $initialTokens)
            {
                $this->tokens = $initialTokens;
            }

            public function load(): ?array
            {
                return $this->tokens;
            }

            public function save(string $accessToken, string $refreshToken): void
            {
                $this->tokens = [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                ];
            }

            public function clear(): void
            {
                $this->tokens = null;
            }

            public function getTokens(): ?array
            {
                return $this->tokens;
            }
        };
    }

    public function testTokenInjection(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"status":"ok"}'),
        ]);

        $easyClient = new EasySQLClient([
            'access_token' => 'access123',
            'refresh_token' => 'refresh456',
            'handler' => $mock,
        ]);

        $response = $easyClient->getHttpClient()->get('/v1/auth/me');
        
        $this->assertSame(200, $response->getStatusCode());

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('Bearer access123', $request->getHeaderLine('Authorization'));
    }

    public function testAutoRefreshOn401Success(): void
    {
        // 1. Initial request gets 401
        // 2. Refresh request gets 200 (returns new tokens)
        // 3. Retried request gets 200
        $mock = new MockHandler([
            new Response(401, [], 'Unauthorized'),
            new Response(200, [], json_encode([
                'access_token' => 'new_access_789',
                'refresh_token' => 'new_refresh_012',
            ])),
            new Response(200, [], 'Success Response'),
        ]);

        $store = $this->createMockStore();

        $easyClient = new EasySQLClient([
            'access_token' => 'expired_access',
            'refresh_token' => 'valid_refresh',
            'handler' => $mock,
        ]);
        $easyClient->setTokenStore($store);

        $response = $easyClient->getHttpClient()->get('/v1/protected/resource');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Success Response', (string) $response->getBody());

        // Check token store has been updated
        $tokens = $store->getTokens();
        $this->assertNotNull($tokens);
        $this->assertSame('new_access_789', $tokens['access_token']);
        $this->assertSame('new_refresh_012', $tokens['refresh_token']);
    }

    public function testAutoRefreshOn401Failure(): void
    {
        // 1. Initial request gets 401
        // 2. Refresh request fails with 400
        $mock = new MockHandler([
            new Response(401, [], 'Unauthorized'),
            new Response(400, [], 'Invalid Refresh Token'),
        ]);

        $store = $this->createMockStore([
            'access_token' => 'expired_access',
            'refresh_token' => 'invalid_refresh',
        ]);

        $easyClient = new EasySQLClient([
            'handler' => $mock,
        ]);
        $easyClient->setTokenStore($store);

        $response = $easyClient->getHttpClient()->get('/v1/protected/resource');

        // Retrying should fail, returning the original 401 response
        $this->assertSame(401, $response->getStatusCode());

        // Stored tokens should have been cleared because refresh failed
        $this->assertNull($store->getTokens());
    }

    public function testTokenStoreLoading(): void
    {
        $store = $this->createMockStore([
            'access_token' => 'stored_access',
            'refresh_token' => 'stored_refresh',
        ]);

        $mock = new MockHandler([
            new Response(200, [], '{"status":"ok"}'),
        ]);

        $easyClient = new EasySQLClient([
            'handler' => $mock,
        ]);
        $easyClient->setTokenStore($store);

        $response = $easyClient->getHttpClient()->get('/v1/protected');
        $this->assertSame(200, $response->getStatusCode());

        $request = $mock->getLastRequest();
        $this->assertSame('Bearer stored_access', $request->getHeaderLine('Authorization'));
    }

    public function testSetTokensSavesToStore(): void
    {
        $store = $this->createMockStore();
        $easyClient = new EasySQLClient();
        $easyClient->setTokenStore($store);

        $easyClient->setTokens('manual_access', 'manual_refresh');

        $tokens = $store->getTokens();
        $this->assertNotNull($tokens);
        $this->assertSame('manual_access', $tokens['access_token']);
        $this->assertSame('manual_refresh', $tokens['refresh_token']);
    }

    public function testClearTokensClearsStore(): void
    {
        $store = $this->createMockStore([
            'access_token' => 'temp_access',
            'refresh_token' => 'temp_refresh',
        ]);
        $easyClient = new EasySQLClient();
        $easyClient->setTokenStore($store);

        $easyClient->clearTokens();

        $this->assertNull($store->getTokens());
    }
}
