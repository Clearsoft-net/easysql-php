<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Tests\DuckDb;

use Clearsoft\EasySQL\Connectors\DuckDb\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\DuckDb\Connector;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    // ── Config ────────────────────────────────────────────────

    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame(':memory:', $config->file);
        $this->assertFalse($config->readOnly);
        $this->assertSame(10.0, $config->timeout);
    }

    public function testDsnFormat(): void
    {
        $this->assertSame('duckdb::memory:', (new ConnectionConfig())->toDsn());

        $this->assertSame(
            'duckdb:/data/analytics.duckdb',
            (new ConnectionConfig(file: '/data/analytics.duckdb'))->toDsn(),
        );
    }

    public function testFromUrlAcceptsFilePathAndUrl(): void
    {
        $this->assertSame(':memory:', ConnectionConfig::fromUrl(':memory:')->file);
        $this->assertSame('/data/analytics.duckdb', ConnectionConfig::fromUrl('/data/analytics.duckdb')->file);
        $this->assertSame(
            '/data/analytics.duckdb',
            ConnectionConfig::fromUrl('duckdb:///data/analytics.duckdb')->file,
        );
    }

    // ── Connection failures ───────────────────────────────────

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

    // ── Integration (requires the pdo_duckdb extension) ───────

    public function testIntegrationAgainstLocalDuckdb(): void
    {
        if (getenv('EASYSQL_TEST_DUCKDB') !== '1') {
            $this->markTestSkipped('Set EASYSQL_TEST_DUCKDB=1 with the pdo_duckdb extension to run integration tests.');
        }

        $connector = new Connector(new ConnectionConfig(file: ':memory:'));
        $connector->connect();

        try {
            $result = $connector->execute('SELECT 1 AS one');
            $this->assertSame(['one'], $result['columns']);
            $this->assertSame(1, $result['row_count']);

            $raw = $connector->introspect();
            $this->assertSame('duckdb', $raw['engine']);
            $this->assertArrayHasKey('tables', $raw);
        } finally {
            $connector->close();
        }
    }
}
