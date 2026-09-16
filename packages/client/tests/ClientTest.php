<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Client\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Clearsoft\EasySQL\Client\Client;
use Clearsoft\EasySQL\Client\Exceptions\ApiException;
use Clearsoft\EasySQL\Client\Http\TokenStoreInterface;

class ClientTest extends TestCase
{
    private function createClient(array $responses, array $config = []): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        // Use reflection to inject the mock handler into the client
        $client = new Client($config);

        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('http');
        $property->setAccessible(true);

        $guzzle = new GuzzleClient(['handler' => $handlerStack, 'http_errors' => false]);
        $property->setValue($client, $guzzle);

        return $client;
    }

    // ── Simple body-only methods ──────────────────────────────

    public function testRefresh(): void
    {
        $client = $this->createClient([
            new Response(200, [], json_encode([
                'access_token' => 'abc123',
                'refresh_token' => 'ref456',
                'token_type' => 'bearer',
            ])),
        ]);

        $result = $client->refresh(['refresh_token' => 'old-token']);

        $this->assertSame('abc123', $result['access_token']);
        $this->assertSame('ref456', $result['refresh_token']);
    }

    public function testLogout(): void
    {
        $client = $this->createClient([
            new Response(200, [], json_encode([
                'end_session_url' => 'https://auth.easysql.net/end-session',
            ])),
        ]);

        $result = $client->logout();

        $this->assertSame('https://auth.easysql.net/end-session', $result['end_session_url']);
    }

    // ── No-param methods ──────────────────────────────────────

    public function testMe(): void
    {
        $client = $this->createClient([
            new Response(200, [], json_encode([
                'id' => 'usr_1',
                'email' => 'test@example.com',
                'name' => 'Test User',
                'locale' => 'en',
                'created_at' => '2025-01-01T00:00:00Z',
            ])),
        ]);

        $result = $client->me();

        $this->assertSame('usr_1', $result['id']);
    }

    // ── Path param methods ────────────────────────────────────

    public function testGetConnector(): void
    {
        $client = $this->createClient([
            new Response(200, [], json_encode([
                'id' => 'conn_1',
                'type' => 'mysql',
                'name' => 'My DB',
                'last_sync_at' => null,
                'created_at' => '2025-01-01T00:00:00Z',
            ])),
        ]);

        $result = $client->getConnector('conn_1');

        $this->assertSame('conn_1', $result['id']);
        $this->assertSame('mysql', $result['type']);
    }

    public function testDeleteConnector(): void
    {
        $client = $this->createClient([
            new Response(204),
        ]);

        $client->deleteConnector('conn_1');

        // No exception means success
        $this->assertTrue(true);
    }

    // ── Query param methods ───────────────────────────────────

    public function testListQueries(): void
    {
        $client = $this->createClient([
            new Response(200, [], json_encode([
                'items' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => 20,
                'total_pages' => 0,
            ])),
        ]);

        $result = $client->listQueries(['page' => 1, 'per_page' => 10]);

        $this->assertSame(0, $result['total']);
        $this->assertSame(1, $result['page']);
    }

    // ── Explicit params (body + path) ─────────────────────────

    public function testUpdateConnector(): void
    {
        $client = $this->createClient([
            new Response(200, [], json_encode([
                'id' => 'conn_1',
                'type' => 'mysql',
                'name' => 'Updated DB',
                'last_sync_at' => null,
                'created_at' => '2025-01-01T00:00:00Z',
            ])),
        ]);

        $result = $client->updateConnector(
            ['name' => 'Updated DB'],
            'conn_1',
        );

        $this->assertSame('Updated DB', $result['name']);
    }

    // ── Error handling ────────────────────────────────────────

    public function testHandles422Error(): void
    {
        $client = $this->createClient([
            new Response(422, [], json_encode([
                'detail' => [['msg' => 'Invalid request']],
            ])),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(422);

        try {
            $client->refresh(['refresh_token' => '']);
        } catch (ApiException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $details = $e->getErrorDetails();
            $this->assertArrayHasKey('detail', $details);
            $this->assertSame('Invalid request', $details['detail'][0]['msg']);
            throw $e;
        }
    }

    public function testCustomHttpClientInjection(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['access_token' => 'custom123', 'refresh_token' => 'ref789'])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $customGuzzle = new GuzzleClient(['handler' => $handlerStack]);

        $client = new Client([
            'http_client' => $customGuzzle,
        ]);

        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('http');
        $property->setAccessible(true);
        $injectedGuzzle = $property->getValue($client);

        $this->assertSame($customGuzzle, $injectedGuzzle);

        $result = $client->refresh(['refresh_token' => 'old-token']);
        $this->assertSame('custom123', $result['access_token']);
    }

    // ── Token handling on the typed client ────────────────────

    private function createTokenStore(?array $initial = null): TokenStoreInterface
    {
        return new class($initial) implements TokenStoreInterface {
            public array $tokens = [];

            public function __construct(?array $initial)
            {
                if ($initial !== null) {
                    $this->tokens = $initial;
                }
            }

            public function load(): ?array
            {
                return $this->tokens === [] ? null : $this->tokens;
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
                $this->tokens = [];
            }
        };
    }

    public function testSetAccessTokenDoesNotRecreateHttpClient(): void
    {
        $client = new Client(['base_url' => 'https://api.easysql.net']);

        $before = $client->getHttpClient();
        $client->setAccessToken('new-token');

        $this->assertSame($before, $client->getHttpClient());
    }

    public function testAutoRefreshOn401WithTokenStore(): void
    {
        $mock = new MockHandler([
            new Response(401, [], 'Unauthorized'),
            new Response(200, [], json_encode([
                'access_token' => 'refreshed_access',
                'refresh_token' => 'refreshed_refresh',
            ])),
            new Response(200, [], json_encode(['id' => 'usr_1', 'email' => 'test@example.com'])),
        ]);

        $store = $this->createTokenStore();

        $client = new Client([
            'access_token' => 'expired',
            'refresh_token' => 'valid_refresh',
            'handler' => $mock,
        ]);
        $client->setTokenStore($store);

        $result = $client->me();

        $this->assertSame('usr_1', $result['id']);
        $this->assertSame('refreshed_access', $store->tokens['access_token']);
        $this->assertSame('refreshed_refresh', $store->tokens['refresh_token']);
    }
}
