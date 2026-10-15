<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Tests\SqlServer;

use Clearsoft\EasySQL\Connectors\SqlServer\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\SqlServer\Connector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame('127.0.0.1', $config->host);
        $this->assertSame(1433, $config->port);
        $this->assertSame('sa', $config->user);
        $this->assertTrue($config->encrypt);
        $this->assertFalse($config->trustServerCertificate);
        $this->assertSame(10.0, $config->timeout);
    }

    public function testDsnFormat(): void
    {
        $config = new ConnectionConfig(
            host: 'db.internal',
            port: 1433,
            database: 'shop',
        );

        $this->assertSame('sqlsrv:Server=db.internal,1433;Database=shop', $config->toDsn());
    }

    public function testFromUrlBuildsConfig(): void
    {
        $config = ConnectionConfig::fromUrl('sqlserver://sa:s3cr3t@db.internal:1433/shop');

        $this->assertSame('db.internal', $config->host);
        $this->assertSame(1433, $config->port);
        $this->assertSame('sa', $config->user);
        $this->assertSame('s3cr3t', $config->password);
        $this->assertSame('shop', $config->database);
    }

    public function testFromUrlRejectsOtherEngine(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionConfig::fromUrl('mysql://root@127.0.0.1:3306/shop');
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

    public function testIntegrationAgainstLocalSqlServer(): void
    {
        if (getenv('EASYSQL_TEST_SQLSERVER') !== '1') {
            $this->markTestSkipped(
                'Set EASYSQL_TEST_SQLSERVER=1 with a local SQL Server to run integration tests.',
            );
        }

        $connector = new Connector(new ConnectionConfig(
            host: getenv('EASYSQL_TEST_SQLSERVER_HOST') ?: '127.0.0.1',
            port: (int) (getenv('EASYSQL_TEST_SQLSERVER_PORT') ?: 1433),
            user: getenv('EASYSQL_TEST_SQLSERVER_USER') ?: 'sa',
            password: getenv('EASYSQL_TEST_SQLSERVER_PASSWORD') ?: '',
            database: getenv('EASYSQL_TEST_SQLSERVER_DATABASE') ?: 'master',
        ));

        $connector->connect();

        try {
            $raw = $connector->introspect();
            $this->assertSame('sqlserver', $raw['engine']);
            $this->assertArrayHasKey('tables', $raw);
        } finally {
            $connector->close();
        }
    }
}
