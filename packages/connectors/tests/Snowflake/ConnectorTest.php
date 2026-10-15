<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Tests\Snowflake;

use Clearsoft\EasySQL\Connectors\Snowflake\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\Snowflake\Connector;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    // ── Config ────────────────────────────────────────────────

    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame('127.0.0.1', $config->host);
        $this->assertSame(443, $config->port);
        $this->assertSame('SnowflakeDSIIDriver', $config->driver);
        $this->assertSame('PUBLIC', $config->schema);
        $this->assertSame(60.0, $config->timeout);
    }

    public function testDsnFormat(): void
    {
        $config = new ConnectionConfig(
            host: 'xy12345.us-east-1.snowflakecomputing.com',
            port: 443,
            user: 'app',
            password: 's3cr3t',
            database: 'ANALYTICS',
        );

        $this->assertSame(
            'odbc:DRIVER={SnowflakeDSIIDriver};SERVER=xy12345.us-east-1.snowflakecomputing.com'
            . ';PORT=443;DATABASE=ANALYTICS;UID=app;PWD=s3cr3t',
            $config->toDsn(),
        );
    }

    public function testExplicitDsnOverridesTheBuiltOne(): void
    {
        $config = new ConnectionConfig(dsn: 'DSN=SnowflakeProd');

        $this->assertSame('odbc:DSN=SnowflakeProd', $config->toDsn());
    }

    public function testFromUrlBuildsConfig(): void
    {
        $config = ConnectionConfig::fromUrl('snowflake://app:s3cr3t@xy12345.us-east-1/ANALYTICS');

        $this->assertSame('xy12345.us-east-1', $config->host);
        $this->assertSame('xy12345.us-east-1', $config->account);
        $this->assertSame(443, $config->port);
        $this->assertSame('app', $config->user);
        $this->assertSame('s3cr3t', $config->password);
        $this->assertSame('ANALYTICS', $config->database);
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

    // ── Integration (requires Snowflake ODBC) ─────────────────

    public function testIntegrationAgainstSnowflake(): void
    {
        if (getenv('EASYSQL_TEST_SNOWFLAKE') !== '1') {
            $this->markTestSkipped('Set EASYSQL_TEST_SNOWFLAKE=1 with Snowflake ODBC to run integration tests.');
        }

        $connector = new Connector(ConnectionConfig::fromUrl((string) getenv('EASYSQL_TEST_SNOWFLAKE_URL')));
        $connector->connect();

        try {
            $raw = $connector->introspect();
            $this->assertSame('snowflake', $raw['engine']);
            $this->assertArrayHasKey('tables', $raw);
        } finally {
            $connector->close();
        }
    }
}
