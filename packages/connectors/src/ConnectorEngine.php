<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors;

/**
 * Engine identifiers implemented by this package.
 *
 * Mirrors the `ConnectorEngine` vocabulary of the TypeScript sibling package
 * and the `engine` field of the schema payload consumed by schema-generation.
 */
final class ConnectorEngine
{
    public const MySQL = 'mysql';
    public const MariaDB = 'mariadb';
    public const PostgreSQL = 'postgresql';
    public const ClickHouse = 'clickhouse';
    public const SQLite = 'sqlite';
    public const SqlServer = 'sqlserver';
    public const Oracle = 'oracle';
    public const Firebird = 'firebird';
    public const Db2 = 'db2';
    public const DuckDB = 'duckdb';
    public const LibSql = 'libsql';
    public const CloudflareD1 = 'd1';
    public const Snowflake = 'snowflake';
    public const BigQuery = 'bigquery';
    public const Trino = 'trino';
    public const SapHana = 'hana';

    /**
     * Every engine identifier known to this package.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::MySQL,
            self::MariaDB,
            self::PostgreSQL,
            self::ClickHouse,
            self::SQLite,
            self::SqlServer,
            self::Oracle,
            self::Firebird,
            self::Db2,
            self::DuckDB,
            self::LibSql,
            self::CloudflareD1,
            self::Snowflake,
            self::BigQuery,
            self::Trino,
            self::SapHana,
        ];
    }
}
