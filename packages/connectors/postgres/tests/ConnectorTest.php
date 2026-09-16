<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Postgres\Tests;

use Clearsoft\EasySQL\Connectors\Postgres\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\Postgres\Connector;
use Clearsoft\EasySQL\Connectors\Postgres\ConnectorException;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    // ── Config ────────────────────────────────────────────────

    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame('127.0.0.1', $config->host);
        $this->assertSame(5432, $config->port);
        $this->assertSame('prefer', $config->sslmode);
        $this->assertSame(['public'], $config->schemas);
        $this->assertSame(10.0, $config->timeout);
    }

    public function testDsnFormat(): void
    {
        $config = new ConnectionConfig(
            host: 'db.internal',
            port: 5433,
            database: 'shop',
            sslmode: 'require',
        );

        $this->assertSame(
            'pgsql:host=db.internal;port=5433;dbname=shop;sslmode=require',
            $config->toDsn(),
        );
    }

    // ── Connection failures ───────────────────────────────────

    public function testConnectionFailureStripsCredentials(): void
    {
        $connector = new Connector(new ConnectionConfig(
            host: '127.0.0.1',
            port: 1, // closed port — connection must fail fast
            user: 'secret_user',
            password: 's3cr3t-p4ss',
            database: 'shop',
            timeout: 1.0,
        ));

        try {
            $connector->connect();
            $this->fail('Expected ConnectorException');
        } catch (ConnectorException $e) {
            $this->assertStringNotContainsString('s3cr3t-p4ss', $e->getMessage());
            $this->assertStringNotContainsString('secret_user', $e->getMessage());
        }
    }

    public function testExecuteWithoutConnectThrows(): void
    {
        $connector = new Connector(new ConnectionConfig());

        $this->expectException(\RuntimeException::class);

        $connector->execute('SELECT 1');
    }

    // ── Integration (requires local PostgreSQL) ───────────────

    private function integrationConfig(): ConnectionConfig
    {
        return new ConnectionConfig(
            host: getenv('EASYSQL_TEST_POSTGRES_HOST') ?: '127.0.0.1',
            port: (int) (getenv('EASYSQL_TEST_POSTGRES_PORT') ?: 5433),
            user: getenv('EASYSQL_TEST_POSTGRES_USER') ?: 'postgres',
            password: getenv('EASYSQL_TEST_POSTGRES_PASSWORD') ?: 'postgres',
            database: getenv('EASYSQL_TEST_POSTGRES_DATABASE') ?: 'easysql_test',
            sslmode: getenv('EASYSQL_TEST_POSTGRES_SSLMODE') ?: 'prefer',
            schemas: ['public'],
        );
    }

    public function testIntegrationAgainstLocalPostgres(): void
    {
        if (getenv('EASYSQL_TEST_POSTGRES') !== '1') {
            $this->markTestSkipped('Set EASYSQL_TEST_POSTGRES=1 with a local PostgreSQL to run integration tests.');
        }

        $connector = new Connector($this->integrationConfig());
        $connector->connect();

        try {
            $raw = $connector->introspect();
            $this->assertArrayHasKey('tables', $raw);

            $tables = array_column($raw['tables'], 'name');
            $this->assertContains('customers', $tables);
            $this->assertContains('orders', $tables);

            $orders = null;
            foreach ($raw['tables'] as $table) {
                if ($table['name'] === 'orders') {
                    $orders = $table;
                }
            }
            $this->assertNotNull($orders);

            $customerId = null;
            foreach ($orders['columns'] as $column) {
                if ($column['name'] === 'customer_id') {
                    $customerId = $column;
                }
            }
            $this->assertNotNull($customerId);
            $this->assertSame(
                ['table' => 'customers', 'column' => 'id'],
                $customerId['foreign_key'],
            );

            $result = $connector->execute('SELECT id, email FROM customers ORDER BY id');
            $this->assertSame(['id', 'email'], $result['columns']);
            $this->assertSame(2, $result['row_count']);
        } finally {
            $connector->close();
        }
    }
}
