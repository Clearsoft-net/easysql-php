<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\SchemaGeneration\Tests;

use Clearsoft\EasySQL\SchemaGeneration\SchemaGenerator;
use Clearsoft\EasySQL\SchemaGeneration\TypeMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Engine-native → canonical type mapping for the engines added in the
 * connector-consolidation work, mirroring the TypeScript sibling
 * `schema.test.ts`.
 */
class TypeMapperTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function typesProvider(): iterable
    {
        // ── MySQL / MariaDB ───────────────────────────────────
        yield 'mysql tinyint(1)' => ['mysql', 'tinyint(1)', 'boolean'];
        yield 'mariadb varchar' => ['mariadb', 'varchar(255)', 'string'];

        // ── SQLite / libSQL / D1 ──────────────────────────────
        yield 'sqlite integer' => ['sqlite', 'INTEGER', 'integer'];
        yield 'sqlite varchar' => ['sqlite', 'VARCHAR(255)', 'text'];
        yield 'sqlite real' => ['sqlite', 'REAL', 'float'];
        yield 'sqlite numeric' => ['sqlite', 'NUMERIC', 'decimal'];
        yield 'sqlite empty' => ['sqlite', '', 'blob'];
        yield 'sqlite datetime' => ['sqlite', 'DATETIME', 'datetime'];
        yield 'sqlite uuid' => ['sqlite', 'UUID', 'uuid'];
        yield 'libsql varchar' => ['libsql', 'VARCHAR(255)', 'text'];
        yield 'libsql boolean' => ['libsql', 'BOOLEAN', 'boolean'];
        yield 'd1 integer' => ['d1', 'INTEGER', 'integer'];
        yield 'd1 json' => ['d1', 'JSON', 'json'];

        // ── PostgreSQL / ClickHouse ───────────────────────────
        yield 'postgres jsonb' => ['postgresql', 'jsonb', 'json'];
        yield 'postgres array' => ['postgresql', 'integer[]', 'json'];
        yield 'clickhouse nullable' => ['clickhouse', 'Nullable(String)', 'string'];
        yield 'clickhouse lowcardinality' => ['clickhouse', 'LowCardinality(String)', 'string'];
        yield 'clickhouse array' => ['clickhouse', 'Array(String)', 'json'];

        // ── SQL Server ────────────────────────────────────────
        yield 'sqlserver int' => ['sqlserver', 'int', 'integer'];
        yield 'sqlserver bigint' => ['sqlserver', 'BIGINT', 'bigint'];
        yield 'sqlserver tinyint' => ['sqlserver', 'tinyint', 'smallint'];
        yield 'sqlserver bit' => ['sqlserver', 'bit', 'boolean'];
        yield 'sqlserver numeric' => ['sqlserver', 'numeric(38, 0)', 'decimal'];
        yield 'sqlserver money' => ['sqlserver', 'money', 'decimal'];
        yield 'sqlserver nvarchar' => ['sqlserver', 'nvarchar(max)', 'string'];
        yield 'sqlserver sysname' => ['sqlserver', 'sysname', 'string'];
        yield 'sqlserver ntext' => ['sqlserver', 'ntext', 'text'];
        yield 'sqlserver image' => ['sqlserver', 'image', 'blob'];
        yield 'sqlserver timestamp is binary' => ['sqlserver', 'timestamp', 'binary'];
        yield 'sqlserver rowversion' => ['sqlserver', 'rowversion', 'binary'];
        yield 'sqlserver datetimeoffset' => ['sqlserver', 'datetimeoffset', 'timestamp'];
        yield 'sqlserver uniqueidentifier' => ['sqlserver', 'uniqueidentifier', 'uuid'];
        yield 'sqlserver sql_variant' => ['sqlserver', 'sql_variant', 'unknown'];
        yield 'sqlserver hierarchyid' => ['sqlserver', 'hierarchyid', 'unknown'];

        // ── Oracle ────────────────────────────────────────────
        yield 'oracle number' => ['oracle', 'NUMBER', 'decimal'];
        yield 'oracle number(p,s)' => ['oracle', 'NUMBER(10,2)', 'decimal'];
        yield 'oracle number(38)' => ['oracle', 'NUMBER(38)', 'bigint'];
        yield 'oracle number(1)' => ['oracle', 'NUMBER(1)', 'integer'];
        yield 'oracle float' => ['oracle', 'FLOAT(126)', 'float'];
        yield 'oracle binary_double' => ['oracle', 'BINARY_DOUBLE', 'float'];
        yield 'oracle varchar2' => ['oracle', 'VARCHAR2(255)', 'string'];
        yield 'oracle rowid' => ['oracle', 'ROWID', 'string'];
        yield 'oracle clob' => ['oracle', 'CLOB', 'text'];
        yield 'oracle long raw' => ['oracle', 'LONG RAW', 'binary'];
        yield 'oracle bfile' => ['oracle', 'BFILE', 'blob'];
        yield 'oracle date is datetime' => ['oracle', 'DATE', 'datetime'];
        yield 'oracle timestamp tz' => ['oracle', 'TIMESTAMP WITH TIME ZONE', 'timestamp'];
        yield 'oracle timestamp ltz' => ['oracle', 'TIMESTAMP(6) WITH LOCAL TIME ZONE', 'timestamp'];
        yield 'oracle interval ytm' => ['oracle', 'INTERVAL YEAR(2) TO MONTH', 'interval'];
        yield 'oracle xmltype' => ['oracle', 'XMLTYPE', 'json'];
        yield 'oracle sdo_geometry' => ['oracle', 'SDO_GEOMETRY', 'unknown'];

        // ── Firebird ──────────────────────────────────────────
        yield 'firebird int128' => ['firebird', 'INT128', 'bigint'];
        yield 'firebird double' => ['firebird', 'DOUBLE PRECISION', 'float'];
        yield 'firebird boolean' => ['firebird', 'BOOLEAN', 'boolean'];
        yield 'firebird blob' => ['firebird', 'BLOB', 'binary'];
        yield 'firebird blob subtype 0' => ['firebird', 'BLOB SUB_TYPE 0', 'binary'];
        yield 'firebird blob subtype 10' => ['firebird', 'BLOB SUB_TYPE 10', 'binary'];
        yield 'firebird blob subtype 1' => ['firebird', 'BLOB SUB_TYPE 1', 'text'];
        yield 'firebird blob subtype text' => ['firebird', 'BLOB SUB_TYPE TEXT', 'text'];
        yield 'firebird blob subtype 1 charset' => ['firebird', 'BLOB SUB_TYPE 1 CHARACTER SET UTF8', 'text'];
        yield 'firebird decfloat' => ['firebird', 'DECFLOAT(16)', 'unknown'];

        // ── Db2 ───────────────────────────────────────────────
        yield 'db2 decfloat' => ['db2', 'DECFLOAT(34)', 'decimal'];
        yield 'db2 graphic' => ['db2', 'GRAPHIC(10)', 'string'];
        yield 'db2 vargraphic' => ['db2', 'VARGRAPHIC(255)', 'string'];
        yield 'db2 dbclob' => ['db2', 'DBCLOB(1M)', 'text'];
        yield 'db2 long varchar' => ['db2', 'LONG VARCHAR', 'text'];
        yield 'db2 xml' => ['db2', 'XML', 'text'];
        yield 'db2 array' => ['db2', 'ARRAY', 'unknown'];

        // ── DuckDB ────────────────────────────────────────────
        yield 'duckdb hugeint' => ['duckdb', 'HUGEINT', 'bigint'];
        yield 'duckdb uinteger' => ['duckdb', 'UINTEGER', 'integer'];
        yield 'duckdb blob' => ['duckdb', 'BLOB', 'binary'];
        yield 'duckdb bit' => ['duckdb', 'BIT', 'string'];
        yield 'duckdb timestamptz' => ['duckdb', 'TIMESTAMP WITH TIME ZONE', 'timestamp'];
        yield 'duckdb timestamp_ns' => ['duckdb', 'TIMESTAMP_NS', 'timestamp'];
        yield 'duckdb struct' => ['duckdb', 'STRUCT(a INTEGER)', 'json'];
        yield 'duckdb map' => ['duckdb', 'MAP(VARCHAR, INTEGER)', 'json'];
        yield 'duckdb list' => ['duckdb', 'LIST(INTEGER)', 'json'];
        yield 'duckdb union' => ['duckdb', 'UNION(a INTEGER)', 'json'];
        yield 'duckdb array' => ['duckdb', 'INTEGER[]', 'json'];
        yield 'duckdb geometry' => ['duckdb', 'GEOMETRY', 'unknown'];

        // ── Snowflake ─────────────────────────────────────────
        yield 'snowflake number' => ['snowflake', 'NUMBER(38,0)', 'decimal'];
        yield 'snowflake integer' => ['snowflake', 'INTEGER', 'decimal'];
        yield 'snowflake real' => ['snowflake', 'REAL', 'float'];
        yield 'snowflake string' => ['snowflake', 'STRING', 'string'];
        yield 'snowflake binary' => ['snowflake', 'BINARY(16)', 'binary'];
        yield 'snowflake timestamp_ntz' => ['snowflake', 'TIMESTAMP_NTZ(9)', 'timestamp'];
        yield 'snowflake timestamp_tz' => ['snowflake', 'TIMESTAMP_TZ', 'timestamp'];
        yield 'snowflake variant' => ['snowflake', 'VARIANT', 'json'];
        yield 'snowflake object' => ['snowflake', 'OBJECT', 'json'];
        yield 'snowflake geography' => ['snowflake', 'GEOGRAPHY', 'unknown'];

        // ── BigQuery ──────────────────────────────────────────
        yield 'bigquery int64' => ['bigquery', 'INT64', 'bigint'];
        yield 'bigquery integer' => ['bigquery', 'INTEGER', 'bigint'];
        yield 'bigquery bignumeric' => ['bigquery', 'BIGNUMERIC', 'decimal'];
        yield 'bigquery bytes' => ['bigquery', 'BYTES', 'binary'];
        yield 'bigquery array' => ['bigquery', 'ARRAY<INT64>', 'json'];
        yield 'bigquery struct' => ['bigquery', 'STRUCT', 'json'];
        yield 'bigquery geography' => ['bigquery', 'GEOGRAPHY', 'unknown'];

        // ── Trino ─────────────────────────────────────────────
        yield 'trino tinyint' => ['trino', 'TINYINT', 'smallint'];
        yield 'trino decimal' => ['trino', 'DECIMAL(38,10)', 'decimal'];
        yield 'trino ipaddress' => ['trino', 'IPADDRESS', 'string'];
        yield 'trino ipprefix' => ['trino', 'IPPREFIX', 'string'];
        yield 'trino time tz' => ['trino', 'TIME(3) WITH TIME ZONE', 'time'];
        yield 'trino timestamp tz' => ['trino', 'TIMESTAMP(3) WITH TIME ZONE', 'timestamp'];
        yield 'trino array' => ['trino', 'ARRAY(BIGINT)', 'json'];
        yield 'trino map' => ['trino', 'MAP(VARCHAR, BIGINT)', 'json'];
        yield 'trino row' => ['trino', 'ROW(a BIGINT, b VARCHAR)', 'json'];
        yield 'trino geometry' => ['trino', 'GEOMETRY', 'unknown'];

        // ── SAP HANA ──────────────────────────────────────────
        yield 'hana tinyint' => ['hana', 'TINYINT', 'smallint'];
        yield 'hana smalldecimal' => ['hana', 'SMALLDECIMAL', 'decimal'];
        yield 'hana alphanum' => ['hana', 'ALPHANUM(10)', 'string'];
        yield 'hana shorttext' => ['hana', 'SHORTTEXT(100)', 'text'];
        yield 'hana seconddate' => ['hana', 'SECONDDATE', 'datetime'];
        yield 'hana array' => ['hana', 'ARRAY(INTEGER)', 'json'];
        yield 'hana st_geometry' => ['hana', 'ST_GEOMETRY', 'unknown'];
    }

    #[DataProvider('typesProvider')]
    public function testMapsEngineNativeType(string $engine, string $raw, string $expected): void
    {
        $this->assertSame($expected, TypeMapper::map($engine, $raw));
    }

    public function testGenerateAcceptsEveryEngine(): void
    {
        $generator = new SchemaGenerator();

        foreach (
            [
                'mysql', 'mariadb', 'sqlite', 'postgresql', 'clickhouse',
                'sqlserver', 'oracle', 'firebird', 'db2', 'duckdb',
                'libsql', 'd1', 'snowflake', 'bigquery', 'trino', 'hana',
            ] as $engine
        ) {
            $payload = $generator->generate([
                'engine' => $engine,
                'tables' => [
                    ['name' => 't', 'columns' => [['name' => 'c', 'dataType' => 'whatever', 'nullable' => true]]],
                ],
            ]);

            $this->assertSame('t', $payload[0]['name'], $engine);
            $this->assertArrayHasKey('type', $payload[0]['columns'][0], $engine);
        }
    }
}
