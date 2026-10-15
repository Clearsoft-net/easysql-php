<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Common\Tests;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConnectionUrlTest extends TestCase
{
    public function testParsesMysqlUrl(): void
    {
        $parsed = ConnectionUrl::parse('mysql://app:s3cr3t@db.internal:3306/shop');

        $this->assertSame('mysql', $parsed['type']);
        $this->assertSame('db.internal', $parsed['host']);
        $this->assertSame(3306, $parsed['port']);
        $this->assertSame('app', $parsed['user']);
        $this->assertSame('s3cr3t', $parsed['password']);
        $this->assertSame('shop', $parsed['database']);
        $this->assertFalse($parsed['ssl']);
    }

    public function testMariadbSchemeMapsToMariadbType(): void
    {
        $this->assertSame('mariadb', ConnectionUrl::parse('mariadb://root@127.0.0.1:3306/app')['type']);
    }

    public function testAppliesEngineDefaultPort(): void
    {
        $this->assertSame(5432, ConnectionUrl::parse('postgresql://postgres@127.0.0.1/shop')['port']);
        $this->assertSame(8123, ConnectionUrl::parse('clickhouse://default@127.0.0.1/shop')['port']);
    }

    public function testPostgresSchemeMapsToPostgresqlType(): void
    {
        $this->assertSame('postgresql', ConnectionUrl::parse('postgres://postgres@127.0.0.1/shop')['type']);
    }

    public function testClickhousesSchemeImpliesTlsAndSecurePort(): void
    {
        $parsed = ConnectionUrl::parse('clickhouses://default@clickhouse.internal/db');

        $this->assertSame('clickhouse', $parsed['type']);
        $this->assertSame(8443, $parsed['port']);
        $this->assertTrue($parsed['ssl']);
    }

    public function testSslQueryParameterForcesTls(): void
    {
        $parsed = ConnectionUrl::parse('clickhouse://default@clickhouse.internal/db?ssl=true');

        $this->assertTrue($parsed['ssl']);
        $this->assertSame(8123, $parsed['port']);
    }

    public function testSslmodeRequireForcesTls(): void
    {
        $this->assertTrue(
            ConnectionUrl::parse('postgresql://postgres@127.0.0.1/shop?sslmode=require')['ssl'],
        );
    }

    public function testParsesSqliteFileUrl(): void
    {
        $parsed = ConnectionUrl::parse('sqlite:///tmp/shop.db');

        $this->assertSame('sqlite', $parsed['type']);
        $this->assertSame('/tmp/shop.db', $parsed['database']);
        $this->assertSame('', $parsed['host']);
        $this->assertSame(0, $parsed['port']);
    }

    public function testRejectsSqliteUrlWithoutPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionUrl::parse('sqlite://');
    }

    public function testRejectsUnsupportedProtocol(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported protocol');

        ConnectionUrl::parse('redis://127.0.0.1:6379');
    }

    public function testRejectsMissingUser(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing database user');

        ConnectionUrl::parse('mysql://127.0.0.1:3306/shop');
    }

    public function testRejectsMissingDatabase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing database name');

        ConnectionUrl::parse('mysql://root@127.0.0.1:3306');
    }

    public function testMergeAppliesOverridesAndDefaults(): void
    {
        $merged = ConnectionUrl::merge(
            ['type' => 'mysql', 'host' => 'db.internal', 'database' => 'shop'],
            ['user' => 'app', 'password' => 's3cr3t'],
        );

        $this->assertSame('mysql', $merged['type']);
        $this->assertSame('db.internal', $merged['host']);
        $this->assertSame(3306, $merged['port']);
        $this->assertSame('app', $merged['user']);
        $this->assertSame('s3cr3t', $merged['password']);
        $this->assertSame('shop', $merged['database']);
    }

    public function testMergeRejectsMissingDatabase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Database name is required.');

        ConnectionUrl::merge(['type' => 'mysql', 'host' => 'db.internal'], ['user' => 'app']);
    }
}
