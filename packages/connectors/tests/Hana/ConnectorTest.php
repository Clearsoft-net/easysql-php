<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Tests\Hana;

use Clearsoft\EasySQL\Connectors\Hana\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\Hana\Connector;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    // ── Config ────────────────────────────────────────────────

    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame('127.0.0.1', $config->host);
        $this->assertSame(30015, $config->port);
        $this->assertSame('HDBODBC', $config->driver);
        $this->assertFalse($config->ssl);
        $this->assertSame(10.0, $config->timeout);
    }

    public function testDsnFormat(): void
    {
        $config = new ConnectionConfig(
            host: 'hana.internal',
            port: 30015,
            user: 'SYSTEM',
            password: 's3cr3t',
            database: 'HDB',
        );

        $this->assertSame(
            'odbc:DRIVER={HDBODBC};SERVER=hana.internal;PORT=30015;DATABASE=HDB;UID=SYSTEM;PWD=s3cr3t',
            $config->toDsn(),
        );
    }

    public function testExplicitDsnOverridesTheBuiltOne(): void
    {
        $config = new ConnectionConfig(dsn: 'DSN=HanaProd');

        $this->assertSame('odbc:DSN=HanaProd', $config->toDsn());
    }

    public function testFromUrlBuildsConfig(): void
    {
        $config = ConnectionConfig::fromUrl('hana://SYSTEM:s3cr3t@hana.internal:30041/HDB');

        $this->assertSame('hana.internal', $config->host);
        $this->assertSame(30041, $config->port);
        $this->assertSame('SYSTEM', $config->user);
        $this->assertSame('s3cr3t', $config->password);
        $this->assertSame('HDB', $config->database);
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

    // ── Integration (requires HANA ODBC) ──────────────────────

    public function testIntegrationAgainstHana(): void
    {
        if (getenv('EASYSQL_TEST_HANA') !== '1') {
            $this->markTestSkipped('Set EASYSQL_TEST_HANA=1 with SAP HANA ODBC to run integration tests.');
        }

        $connector = new Connector(ConnectionConfig::fromUrl((string) getenv('EASYSQL_TEST_HANA_URL')));
        $connector->connect();

        try {
            $raw = $connector->introspect();
            $this->assertSame('hana', $raw['engine']);
            $this->assertArrayHasKey('tables', $raw);
        } finally {
            $connector->close();
        }
    }
}
