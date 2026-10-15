<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Tests\Trino;

use Clearsoft\EasySQL\Common\SqlValidator;
use Clearsoft\EasySQL\Connectors\ConnectorException;
use Clearsoft\EasySQL\Connectors\Trino\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\Trino\Connector;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    // ── Config ────────────────────────────────────────────────

    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame('127.0.0.1', $config->host);
        $this->assertSame(8080, $config->port);
        $this->assertSame('admin', $config->user);
        $this->assertFalse($config->ssl);
        $this->assertSame('', $config->catalog);
        $this->assertSame('', $config->schema);
        $this->assertSame(10.0, $config->timeout);
    }

    public function testSslBuildsHttpsBaseUrl(): void
    {
        $config = new ConnectionConfig(host: 'trino.internal', ssl: true);

        $this->assertSame('https://trino.internal:8080', $config->baseUrl());
    }

    public function testExplicitPortOverridesTheDefault(): void
    {
        $config = new ConnectionConfig(host: 'trino.internal', port: 8443, ssl: true);

        $this->assertSame('https://trino.internal:8443', $config->baseUrl());
    }

    public function testFullHostUrlIsPreserved(): void
    {
        $config = new ConnectionConfig(host: 'https://trino.internal');

        $this->assertSame('https://trino.internal:8080', $config->baseUrl());
    }

    public function testFromUrlBuildsConfig(): void
    {
        $config = ConnectionConfig::fromUrl('trino://analyst@trino.internal:8443/warehouse/sales?ssl=true');

        $this->assertSame('trino.internal', $config->host);
        $this->assertSame(8443, $config->port);
        $this->assertSame('analyst', $config->user);
        $this->assertTrue($config->ssl);
        $this->assertSame('warehouse', $config->catalog);
        $this->assertSame('sales', $config->schema);
    }

    public function testFromUrlRejectsAnotherEngine(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConnectionConfig::fromUrl('mysql://root@127.0.0.1:3306/shop');
    }

    // ── Validator ─────────────────────────────────────────────

    public function testValidatorAcceptsSelect(): void
    {
        $this->assertTrue(SqlValidator::validateSelectOnly('SELECT * FROM orders LIMIT 10')['ok']);
    }

    public function testValidatorRejectsMutations(): void
    {
        foreach (['DELETE FROM orders', 'DROP TABLE orders', 'INSERT INTO orders VALUES (1)'] as $sql) {
            $this->assertFalse(SqlValidator::validateSelectOnly($sql)['ok'], $sql);
        }
    }

    // ── Connection failures ───────────────────────────────────

    public function testConnectionFailureStripsCredentials(): void
    {
        $connector = new Connector(new ConnectionConfig(
            host: '127.0.0.1',
            port: 1, // closed port — connection must fail fast
            user: 'secret_user',
            password: 's3cr3t-p4ss',
            timeout: 1.0,
        ));

        try {
            $connector->connect();
            $this->fail('Expected ConnectorException');
        } catch (ConnectorException $e) {
            $this->assertStringNotContainsString('s3cr3t-p4ss', $e->getMessage());
            $this->assertStringNotContainsString('secret_user', $e->getMessage());
            $this->assertStringContainsString('Trino', $e->getMessage());
        }
    }

    public function testExecuteWithoutConnectThrows(): void
    {
        $connector = new Connector(new ConnectionConfig());

        $this->expectException(\RuntimeException::class);

        $connector->execute('SELECT 1');
    }

    public function testIntrospectWithoutConnectThrows(): void
    {
        $connector = new Connector(new ConnectionConfig());

        $this->expectException(\RuntimeException::class);

        $connector->introspect();
    }

    // ── Integration (requires a local Trino coordinator) ──────

    public function testIntegrationAgainstLocalTrino(): void
    {
        if (getenv('EASYSQL_TEST_TRINO') !== '1') {
            $this->markTestSkipped('Set EASYSQL_TEST_TRINO=1 with a local Trino to run integration tests.');
        }

        $connector = new Connector(new ConnectionConfig(
            host: getenv('EASYSQL_TEST_TRINO_HOST') ?: '127.0.0.1',
            port: (int) (getenv('EASYSQL_TEST_TRINO_PORT') ?: 8080),
            user: getenv('EASYSQL_TEST_TRINO_USER') ?: 'admin',
            password: getenv('EASYSQL_TEST_TRINO_PASSWORD') ?: '',
            ssl: getenv('EASYSQL_TEST_TRINO_SSL') === '1',
            catalog: getenv('EASYSQL_TEST_TRINO_CATALOG') ?: '',
            schema: getenv('EASYSQL_TEST_TRINO_SCHEMA') ?: '',
        ));
        $connector->connect();

        try {
            $raw = $connector->introspect();
            $this->assertSame('trino', $raw['engine']);
            $this->assertArrayHasKey('tables', $raw);

            $result = $connector->execute('SELECT 1 AS one');
            $this->assertSame(['one'], $result['columns']);
            $this->assertSame(1, $result['row_count']);
        } finally {
            $connector->close();
        }
    }
}
