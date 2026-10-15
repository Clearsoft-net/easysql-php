<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Tests;

use Clearsoft\EasySQL\Connectors\ClickHouse\Connector as ClickHouseConnector;
use Clearsoft\EasySQL\Connectors\ConnectorEngine;
use Clearsoft\EasySQL\Connectors\ConnectorFactory;
use Clearsoft\EasySQL\Connectors\ConnectorInterface;
use Clearsoft\EasySQL\Connectors\MySQL\Connector as MySQLConnector;
use Clearsoft\EasySQL\Connectors\Postgres\Connector as PostgresConnector;
use Clearsoft\EasySQL\Connectors\SQLite\Connector as SQLiteConnector;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConnectorFactoryTest extends TestCase
{
    public function testBuildsEachEngineFromAConfigObject(): void
    {
        $this->assertInstanceOf(
            MySQLConnector::class,
            ConnectorFactory::create(
                ConnectorEngine::MySQL,
                new \Clearsoft\EasySQL\Connectors\MySQL\ConnectionConfig(),
            ),
        );
        $this->assertInstanceOf(
            PostgresConnector::class,
            ConnectorFactory::create(
                ConnectorEngine::PostgreSQL,
                new \Clearsoft\EasySQL\Connectors\Postgres\ConnectionConfig(),
            ),
        );
        $this->assertInstanceOf(
            SQLiteConnector::class,
            ConnectorFactory::create(
                ConnectorEngine::SQLite,
                new \Clearsoft\EasySQL\Connectors\SQLite\ConnectionConfig(),
            ),
        );
        $this->assertInstanceOf(
            ClickHouseConnector::class,
            ConnectorFactory::create(
                ConnectorEngine::ClickHouse,
                new \Clearsoft\EasySQL\Connectors\ClickHouse\ConnectionConfig(),
            ),
        );
    }

    public function testBuildsFromAnOptionsArray(): void
    {
        $connector = ConnectorFactory::create(ConnectorEngine::SQLite, ['path' => ':memory:', 'readOnly' => false]);

        $this->assertInstanceOf(ConnectorInterface::class, $connector);
        $this->assertInstanceOf(SQLiteConnector::class, $connector);
    }

    public function testBuildsFromConnectionUrlStrings(): void
    {
        $this->assertInstanceOf(
            MySQLConnector::class,
            ConnectorFactory::create(ConnectorEngine::MySQL, 'mysql://app:s3cr3t@db.internal:3306/shop'),
        );
        $this->assertInstanceOf(
            MySQLConnector::class,
            ConnectorFactory::create(ConnectorEngine::MariaDB, 'mariadb://app:s3cr3t@db.internal:3306/shop'),
        );
        $this->assertInstanceOf(
            PostgresConnector::class,
            ConnectorFactory::create(ConnectorEngine::PostgreSQL, 'postgresql://postgres:secret@db.internal:5432/shop'),
        );
        $this->assertInstanceOf(
            ClickHouseConnector::class,
            ConnectorFactory::create(ConnectorEngine::ClickHouse, 'clickhouse://default@db.internal:8123/events'),
        );
        $this->assertInstanceOf(
            SQLiteConnector::class,
            ConnectorFactory::create(ConnectorEngine::SQLite, 'sqlite:///tmp/shop.db'),
        );
    }

    public function testRejectsAUrlForAnotherEngine(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a mysql/mariadb connection string');

        ConnectorFactory::create(ConnectorEngine::MySQL, 'postgresql://postgres@db.internal/shop');
    }

    public function testMariaDbResolvesToTheMysqlConnector(): void
    {
        $connector = ConnectorFactory::create(
            ConnectorEngine::MariaDB,
            new \Clearsoft\EasySQL\Connectors\MySQL\ConnectionConfig(engine: 'mariadb'),
        );

        $this->assertInstanceOf(MySQLConnector::class, $connector);
    }

    public function testRejectsAnUnknownEngine(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported engine');

        ConnectorFactory::create('redis', ['user' => 'x', 'database' => 'y']);
    }

    public function testRejectsAnArrayConfigForTheWrongEngine(): void
    {
        // PostgreSQL's config has no `path` key, so an array keyed for SQLite
        // is passed to the Postgres config constructor and fails loudly.
        $this->expectException(\Error::class);

        ConnectorFactory::create(ConnectorEngine::PostgreSQL, ['path' => '/tmp/x.db']);
    }

    /**
     * Every engine reachable through the factory, with a URL/DSN string it
     * accepts. Guards the factory dispatch against a wrong class or a missing
     * engine registration.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function engineUrlProvider(): iterable
    {
        yield 'mysql' => [ConnectorEngine::MySQL, 'mysql://u:p@h:3306/db'];
        yield 'mariadb' => [ConnectorEngine::MariaDB, 'mariadb://u:p@h:3306/db'];
        yield 'postgresql' => [ConnectorEngine::PostgreSQL, 'postgresql://u:p@h:5432/db'];
        yield 'clickhouse' => [ConnectorEngine::ClickHouse, 'clickhouse://u:p@h:8123/db'];
        yield 'sqlite' => [ConnectorEngine::SQLite, 'sqlite:///tmp/x.db'];
        yield 'sqlserver' => [ConnectorEngine::SqlServer, 'sqlserver://u:p@h:1433/db'];
        yield 'oracle' => [ConnectorEngine::Oracle, 'oracle://u:p@h:1521/svc'];
        yield 'firebird' => [ConnectorEngine::Firebird, 'firebird://u:p@h:3050/db'];
        yield 'db2' => [ConnectorEngine::Db2, 'db2://u:p@h:50000/db'];
        yield 'duckdb' => [ConnectorEngine::DuckDB, 'duckdb:///tmp/x.db'];
        yield 'snowflake' => [ConnectorEngine::Snowflake, 'snowflake://u:p@h/db'];
        yield 'trino' => [ConnectorEngine::Trino, 'trino://u@h:8080/catalog'];
        yield 'hana' => [ConnectorEngine::SapHana, 'hana://u:p@h:30015'];
        yield 'bigquery' => [ConnectorEngine::BigQuery, 'bigquery://proj/dataset'];
        yield 'd1' => [ConnectorEngine::CloudflareD1, 'd1://token@account/db'];
        yield 'libsql' => [ConnectorEngine::LibSql, 'libsql://token@host'];
    }

    #[DataProvider('engineUrlProvider')]
    public function testBuildsEveryEngineFromAUrl(string $engine, string $url): void
    {
        $this->assertInstanceOf(ConnectorInterface::class, ConnectorFactory::create($engine, $url));
    }
}
