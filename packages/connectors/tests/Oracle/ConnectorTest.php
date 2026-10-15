<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Tests\Oracle;

use Clearsoft\EasySQL\Connectors\Oracle\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\Oracle\Connector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame('127.0.0.1', $config->host);
        $this->assertSame(1521, $config->port);
        $this->assertSame('system', $config->user);
        $this->assertSame(10.0, $config->timeout);
    }

    public function testDsnFormat(): void
    {
        $config = new ConnectionConfig(
            host: 'db.internal',
            port: 1521,
            database: 'ORCL',
        );

        $this->assertSame('oci:dbname=//db.internal:1521/ORCL', $config->toDsn());
    }

    public function testFromUrlBuildsConfig(): void
    {
        $config = ConnectionConfig::fromUrl('oracle://app:s3cr3t@db.internal:1521/ORCL');

        $this->assertSame('db.internal', $config->host);
        $this->assertSame(1521, $config->port);
        $this->assertSame('app', $config->user);
        $this->assertSame('s3cr3t', $config->password);
        $this->assertSame('ORCL', $config->database);
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

        $connector->execute('SELECT 1 FROM dual');
    }

    public function testIntrospectWithoutConnectThrows(): void
    {
        $connector = new Connector(new ConnectionConfig());

        $this->expectException(\RuntimeException::class);

        $connector->introspect();
    }

    public function testIntegrationAgainstLocalOracle(): void
    {
        if (getenv('EASYSQL_TEST_ORACLE') !== '1') {
            $this->markTestSkipped(
                'Set EASYSQL_TEST_ORACLE=1 with a local Oracle Database to run integration tests.',
            );
        }

        $connector = new Connector(new ConnectionConfig(
            host: getenv('EASYSQL_TEST_ORACLE_HOST') ?: '127.0.0.1',
            port: (int) (getenv('EASYSQL_TEST_ORACLE_PORT') ?: 1521),
            user: getenv('EASYSQL_TEST_ORACLE_USER') ?: 'system',
            password: getenv('EASYSQL_TEST_ORACLE_PASSWORD') ?: '',
            database: getenv('EASYSQL_TEST_ORACLE_DATABASE') ?: 'FREEPDB1',
        ));

        $connector->connect();

        try {
            $raw = $connector->introspect();
            $this->assertSame('oracle', $raw['engine']);
            $this->assertArrayHasKey('tables', $raw);
        } finally {
            $connector->close();
        }
    }
}
