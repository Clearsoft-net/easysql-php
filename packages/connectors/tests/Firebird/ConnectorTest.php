<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Tests\Firebird;

use Clearsoft\EasySQL\Connectors\Firebird\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\Firebird\Connector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame('localhost', $config->host);
        $this->assertSame(3050, $config->port);
        $this->assertSame('sysdba', $config->user);
        $this->assertSame('', $config->role);
        $this->assertSame(10.0, $config->timeout);
    }

    public function testDsnFormat(): void
    {
        $config = new ConnectionConfig(
            host: 'db.internal',
            port: 3050,
            database: 'app.fdb',
        );

        $this->assertSame('firebird:dbname=db.internal/3050:app.fdb', $config->toDsn());
    }

    public function testFromUrlBuildsConfig(): void
    {
        $config = ConnectionConfig::fromUrl('firebird://sysdba:masterkey@db.internal:3050/app.fdb');

        $this->assertSame('db.internal', $config->host);
        $this->assertSame(3050, $config->port);
        $this->assertSame('sysdba', $config->user);
        $this->assertSame('masterkey', $config->password);
        $this->assertSame('app.fdb', $config->database);
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

        $connector->execute('SELECT 1 FROM RDB$DATABASE');
    }

    public function testIntrospectWithoutConnectThrows(): void
    {
        $connector = new Connector(new ConnectionConfig());

        $this->expectException(\RuntimeException::class);

        $connector->introspect();
    }

    public function testIntegrationAgainstLocalFirebird(): void
    {
        if (getenv('EASYSQL_TEST_FIREBIRD') !== '1') {
            $this->markTestSkipped(
                'Set EASYSQL_TEST_FIREBIRD=1 with a local Firebird to run integration tests.',
            );
        }

        $connector = new Connector(new ConnectionConfig(
            host: getenv('EASYSQL_TEST_FIREBIRD_HOST') ?: 'localhost',
            port: (int) (getenv('EASYSQL_TEST_FIREBIRD_PORT') ?: 3050),
            user: getenv('EASYSQL_TEST_FIREBIRD_USER') ?: 'sysdba',
            password: getenv('EASYSQL_TEST_FIREBIRD_PASSWORD') ?: 'masterkey',
            database: getenv('EASYSQL_TEST_FIREBIRD_DATABASE') ?: 'employee.fdb',
        ));

        $connector->connect();

        try {
            $raw = $connector->introspect();
            $this->assertSame('firebird', $raw['engine']);
            $this->assertArrayHasKey('tables', $raw);
        } finally {
            $connector->close();
        }
    }
}
