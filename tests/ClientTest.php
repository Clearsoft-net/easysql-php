<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\SDK\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Clearsoft\EasySQL\SDK\Client;

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

    public function testLogin(): void
    {
        $client = $this->createClient([
            new Response(200, [], json_encode([
                'access_token' => 'abc123',
                'refresh_token' => 'ref456',
                'token_type' => 'bearer',
            ])),
        ]);

        $result = $client->login(['email' => 'test@example.com', 'password' => 'secret']);

        $this->assertSame('abc123', $result['access_token']);
        $this->assertSame('ref456', $result['refresh_token']);
    }

    public function testRegister(): void
    {
        $client = $this->createClient([
            new Response(201, [], json_encode([
                'id' => 'usr_1',
                'email' => 'new@example.com',
                'name' => 'New User',
                'locale' => 'en',
                'created_at' => '2025-01-01T00:00:00Z',
            ])),
        ]);

        $result = $client->register([
            'email' => 'new@example.com',
            'name' => 'New User',
            'password' => 'secret123',
        ]);

        $this->assertSame('usr_1', $result['id']);
        $this->assertSame('new@example.com', $result['email']);
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
                'detail' => [['msg' => 'Invalid email']],
            ])),
        ]);

        $result = $client->login(['email' => 'bad', 'password' => 'x']);

        $this->assertArrayHasKey('detail', $result);
    }
}
