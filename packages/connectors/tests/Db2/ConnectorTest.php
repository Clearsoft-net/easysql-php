<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Tests\Db2;

use Clearsoft\EasySQL\Connectors\Db2\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\Db2\Connector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame('localhost', $config->host);
        $this->assertSame(50000, $config->port);
        $this->assertSame('db2inst1', $config->user);
        $this->assertFalse($config->ssl);
        $this->assertSame(10.0, $config->timeout);
    }

    public function testDsnFormat(): void
    {
        $config = new ConnectionConfig(
            host: 'db.internal',
            port: 50000,
            user: 'db2inst1',
            password: 's3cr3t',
            database: 'SAMPLE',
        );

        $this->assertSame(
            'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=SAMPLE;HOSTNAME=db.internal;'
            . 'PORT=50000;PROTOCOL=TCPIP;UID=db2inst1;PWD=s3cr3t',
            $config->toDsn(),
        );
    }

    public function testFromUrlBuildsConfig(): void
    {
        $config = ConnectionConfig::fromUrl('db2://db2inst1:s3cr3t@db.internal:50000/SAMPLE');

        $this->assertSame('db.internal', $config->host);
        $this->assertSame(50000, $config->port);
        $this->assertSame('db2inst1', $config->user);
        $this->assertSame('s3cr3t', $config->password);
        $this->assertSame('SAMPLE', $config->database);
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

        $connector->execute('SELECT 1 FROM SYSIBM.SYSDUMMY1');
    }

    public function testIntrospectWithoutConnectThrows(): void
    {
        $connector = new Connector(new ConnectionConfig());

        $this->expectException(\RuntimeException::class);

        $connector->introspect();
    }

    public function testIntegrationAgainstLocalDb2(): void
    {
        if (getenv('EASYSQL_TEST_DB2') !== '1') {
            $this->markTestSkipped(
                'Set EASYSQL_TEST_DB2=1 with a local Db2 to run integration tests.',
            );
        }

        $connector = new Connector(new ConnectionConfig(
            host: getenv('EASYSQL_TEST_DB2_HOST') ?: 'localhost',
            port: (int) (getenv('EASYSQL_TEST_DB2_PORT') ?: 50000),
            user: getenv('EASYSQL_TEST_DB2_USER') ?: 'db2inst1',
            password: getenv('EASYSQL_TEST_DB2_PASSWORD') ?: '',
            database: getenv('EASYSQL_TEST_DB2_DATABASE') ?: 'SAMPLE',
        ));

        $connector->connect();

        try {
            $raw = $connector->introspect();
            $this->assertSame('db2', $raw['engine']);
            $this->assertArrayHasKey('tables', $raw);
        } finally {
            $connector->close();
        }
    }
}
