<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors;

use Clearsoft\EasySQL\Connectors\BigQuery\ConnectionConfig as BigQueryConfig;
use Clearsoft\EasySQL\Connectors\BigQuery\Connector as BigQueryConnector;
use Clearsoft\EasySQL\Connectors\ClickHouse\ConnectionConfig as ClickHouseConfig;
use Clearsoft\EasySQL\Connectors\ClickHouse\Connector as ClickHouseConnector;
use Clearsoft\EasySQL\Connectors\D1\ConnectionConfig as D1Config;
use Clearsoft\EasySQL\Connectors\D1\Connector as D1Connector;
use Clearsoft\EasySQL\Connectors\Db2\ConnectionConfig as Db2Config;
use Clearsoft\EasySQL\Connectors\Db2\Connector as Db2Connector;
use Clearsoft\EasySQL\Connectors\DuckDb\ConnectionConfig as DuckDbConfig;
use Clearsoft\EasySQL\Connectors\DuckDb\Connector as DuckDbConnector;
use Clearsoft\EasySQL\Connectors\Firebird\ConnectionConfig as FirebirdConfig;
use Clearsoft\EasySQL\Connectors\Firebird\Connector as FirebirdConnector;
use Clearsoft\EasySQL\Connectors\Hana\ConnectionConfig as HanaConfig;
use Clearsoft\EasySQL\Connectors\Hana\Connector as HanaConnector;
use Clearsoft\EasySQL\Connectors\LibSql\ConnectionConfig as LibSqlConfig;
use Clearsoft\EasySQL\Connectors\LibSql\Connector as LibSqlConnector;
use Clearsoft\EasySQL\Connectors\MySQL\ConnectionConfig as MySQLConfig;
use Clearsoft\EasySQL\Connectors\MySQL\Connector as MySQLConnector;
use Clearsoft\EasySQL\Connectors\Oracle\ConnectionConfig as OracleConfig;
use Clearsoft\EasySQL\Connectors\Oracle\Connector as OracleConnector;
use Clearsoft\EasySQL\Connectors\Postgres\ConnectionConfig as PostgresConfig;
use Clearsoft\EasySQL\Connectors\Postgres\Connector as PostgresConnector;
use Clearsoft\EasySQL\Connectors\Snowflake\ConnectionConfig as SnowflakeConfig;
use Clearsoft\EasySQL\Connectors\Snowflake\Connector as SnowflakeConnector;
use Clearsoft\EasySQL\Connectors\SQLite\ConnectionConfig as SQLiteConfig;
use Clearsoft\EasySQL\Connectors\SQLite\Connector as SQLiteConnector;
use Clearsoft\EasySQL\Connectors\SqlServer\ConnectionConfig as SqlServerConfig;
use Clearsoft\EasySQL\Connectors\SqlServer\Connector as SqlServerConnector;
use Clearsoft\EasySQL\Connectors\Trino\ConnectionConfig as TrinoConfig;
use Clearsoft\EasySQL\Connectors\Trino\Connector as TrinoConnector;
use InvalidArgumentException;

/**
 * Builds a connector for the selected engine.
 *
 * The TypeScript sibling package lazily imports only the chosen engine's module
 * (so a consumer pays for one optional driver). PHP has no equivalent optional
 * dependency per engine; instead, the engine extensions are declared under
 * `suggest` and this factory selects one engine from a single entry point.
 *
 * A connection may be given as an engine `ConnectionConfig` object, an explicit
 * options array, or an engine-native URL/DSN string (parsed by
 * {@see \Clearsoft\EasySQL\Common\ConnectionUrl} and mapped onto the config by
 * the engine's {@see ConnectionConfigInterface::fromUrl}).
 */
final class ConnectorFactory
{
    /**
     * @param string $engine One of {@see ConnectorEngine}'s identifiers.
     * @param string|object|array<string, mixed> $connection A connection URL/DSN
     *   string, an engine ConnectionConfig object, or an options array matching
     *   that config's constructor.
     */
    public static function create(string $engine, string|object|array $connection): ConnectorInterface
    {
        return match ($engine) {
            ConnectorEngine::MySQL, ConnectorEngine::MariaDB => new MySQLConnector(
                self::config($connection, MySQLConfig::class),
            ),
            ConnectorEngine::PostgreSQL => new PostgresConnector(
                self::config($connection, PostgresConfig::class),
            ),
            ConnectorEngine::ClickHouse => new ClickHouseConnector(
                self::config($connection, ClickHouseConfig::class),
            ),
            ConnectorEngine::SQLite => new SQLiteConnector(
                self::config($connection, SQLiteConfig::class),
            ),
            ConnectorEngine::SqlServer => new SqlServerConnector(
                self::config($connection, SqlServerConfig::class),
            ),
            ConnectorEngine::Oracle => new OracleConnector(
                self::config($connection, OracleConfig::class),
            ),
            ConnectorEngine::Firebird => new FirebirdConnector(
                self::config($connection, FirebirdConfig::class),
            ),
            ConnectorEngine::Db2 => new Db2Connector(
                self::config($connection, Db2Config::class),
            ),
            ConnectorEngine::DuckDB => new DuckDbConnector(
                self::config($connection, DuckDbConfig::class),
            ),
            ConnectorEngine::LibSql => new LibSqlConnector(
                self::config($connection, LibSqlConfig::class),
            ),
            ConnectorEngine::CloudflareD1 => new D1Connector(
                self::config($connection, D1Config::class),
            ),
            ConnectorEngine::Snowflake => new SnowflakeConnector(
                self::config($connection, SnowflakeConfig::class),
            ),
            ConnectorEngine::BigQuery => new BigQueryConnector(
                self::config($connection, BigQueryConfig::class),
            ),
            ConnectorEngine::Trino => new TrinoConnector(
                self::config($connection, TrinoConfig::class),
            ),
            ConnectorEngine::SapHana => new HanaConnector(
                self::config($connection, HanaConfig::class),
            ),
            default => throw new InvalidArgumentException(
                sprintf(
                    "Unsupported engine '%s'. Supported engines: %s.",
                    $engine,
                    implode(', ', ConnectorEngine::all()),
                ),
            ),
        };
    }

    /**
     * @template T of ConnectionConfigInterface
     * @param string|object|array<string, mixed> $connection
     * @param class-string<T> $configClass
     * @return T
     */
    private static function config(string|object|array $connection, string $configClass): object
    {
        if ($connection instanceof $configClass) {
            return $connection;
        }

        if (is_string($connection)) {
            return $configClass::fromUrl($connection);
        }

        if (is_array($connection)) {
            /** @var T */
            return new $configClass(...$connection);
        }

        throw new InvalidArgumentException(
            sprintf('Expected a %s, an options array or a connection URL string.', $configClass),
        );
    }
}
