<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\SchemaGeneration;

/**
 * Engine-native type → canonical schema type maps.
 *
 * Mirrors the TypeScript sibling package (`type-map.ts`). The maps are
 * exhaustive over the types the connectors are expected to report; anything
 * else falls back to `unknown` so output never depends on an engine-specific
 * spelling. Matching is case-insensitive, ignores length/precision parameters
 * (`varchar(255)` → `varchar`) and, for SQLite, follows the declared-type
 * affinity rules from the SQLite documentation.
 */
final class TypeMapper
{
    /** @var array<string, string> */
    private const MYSQL_TYPES = [
        // integers
        'tinyint' => 'smallint',
        'smallint' => 'smallint',
        'mediumint' => 'integer',
        'int' => 'integer',
        'integer' => 'integer',
        'bigint' => 'bigint',
        'bit' => 'integer',
        'year' => 'integer',
        // booleans
        'bool' => 'boolean',
        'boolean' => 'boolean',
        // numerics
        'decimal' => 'decimal',
        'dec' => 'decimal',
        'numeric' => 'decimal',
        'fixed' => 'decimal',
        'float' => 'float',
        'double' => 'float',
        'real' => 'float',
        // strings
        'char' => 'string',
        'varchar' => 'string',
        'tinytext' => 'text',
        'text' => 'text',
        'mediumtext' => 'text',
        'longtext' => 'text',
        'enum' => 'enum',
        'set' => 'enum',
        // binary
        'binary' => 'binary',
        'varbinary' => 'binary',
        'tinyblob' => 'blob',
        'blob' => 'blob',
        'mediumblob' => 'blob',
        'longblob' => 'blob',
        // temporal
        'date' => 'date',
        'time' => 'time',
        'datetime' => 'datetime',
        'timestamp' => 'timestamp',
        // documents
        'json' => 'json',
        'uuid' => 'uuid',
    ];

    /** @var array<string, string> */
    private const POSTGRES_TYPES = [
        // integers
        'smallint' => 'smallint',
        'int2' => 'smallint',
        'smallserial' => 'smallint',
        'serial2' => 'smallint',
        'integer' => 'integer',
        'int' => 'integer',
        'int4' => 'integer',
        'serial' => 'integer',
        'serial4' => 'integer',
        'bigint' => 'bigint',
        'int8' => 'bigint',
        'bigserial' => 'bigint',
        'serial8' => 'bigint',
        // booleans
        'boolean' => 'boolean',
        'bool' => 'boolean',
        // numerics
        'numeric' => 'decimal',
        'decimal' => 'decimal',
        'money' => 'decimal',
        'real' => 'float',
        'float4' => 'float',
        'double precision' => 'float',
        'float8' => 'float',
        // strings
        'character varying' => 'string',
        'varchar' => 'string',
        'character' => 'string',
        'char' => 'string',
        'bpchar' => 'string',
        'name' => 'string',
        'citext' => 'string',
        'text' => 'text',
        'xml' => 'text',
        // network / identifiers kept as text
        'inet' => 'string',
        'cidr' => 'string',
        'macaddr' => 'string',
        'macaddr8' => 'string',
        // binary
        'bytea' => 'binary',
        // temporal
        'date' => 'date',
        'time' => 'time',
        'timetz' => 'time',
        'timestamp' => 'timestamp',
        'timestamptz' => 'timestamp',
        'interval' => 'interval',
        // documents
        'json' => 'json',
        'jsonb' => 'json',
        'uuid' => 'uuid',
    ];

    /** @var array<string, string> */
    private const CLICKHOUSE_TYPES = [
        // integers
        'int8' => 'smallint',
        'int16' => 'smallint',
        'int32' => 'integer',
        'int64' => 'bigint',
        'int128' => 'bigint',
        'int256' => 'bigint',
        'uint8' => 'smallint',
        'uint16' => 'smallint',
        'uint32' => 'integer',
        'uint64' => 'bigint',
        'uint128' => 'bigint',
        'uint256' => 'bigint',
        // booleans
        'bool' => 'boolean',
        'boolean' => 'boolean',
        // numerics
        'float32' => 'float',
        'float64' => 'float',
        'decimal' => 'decimal',
        'decimal32' => 'decimal',
        'decimal64' => 'decimal',
        'decimal128' => 'decimal',
        'decimal256' => 'decimal',
        // strings
        'string' => 'string',
        'fixedstring' => 'string',
        // network / identifiers kept as text
        'ipv4' => 'string',
        'ipv6' => 'string',
        // temporal
        'date' => 'date',
        'date32' => 'date',
        'datetime' => 'timestamp',
        'datetime64' => 'timestamp',
        // documents
        'uuid' => 'uuid',
        'json' => 'json',
        'object' => 'json',
        'enum8' => 'enum',
        'enum16' => 'enum',
    ];

    /** @var array<string, string> */
    private const SQLSERVER_TYPES = [
        // integers
        'tinyint' => 'smallint',
        'smallint' => 'smallint',
        'int' => 'integer',
        'bigint' => 'bigint',
        // booleans — `bit` is the only real boolean; `tinyint` is an unsigned byte.
        'bit' => 'boolean',
        // numerics
        'decimal' => 'decimal',
        'numeric' => 'decimal',
        'money' => 'decimal',
        'smallmoney' => 'decimal',
        'float' => 'float',
        'real' => 'float',
        // strings
        'char' => 'string',
        'varchar' => 'string',
        'nchar' => 'string',
        'nvarchar' => 'string',
        'sysname' => 'string',
        'text' => 'text',
        'ntext' => 'text',
        'xml' => 'text',
        // binary — `timestamp` is the deprecated rowversion alias, not temporal.
        'binary' => 'binary',
        'varbinary' => 'binary',
        'timestamp' => 'binary',
        'rowversion' => 'binary',
        'image' => 'blob',
        // temporal
        'date' => 'date',
        'time' => 'time',
        'datetime' => 'datetime',
        'datetime2' => 'datetime',
        'smalldatetime' => 'datetime',
        'datetimeoffset' => 'timestamp',
        // identifiers / documents
        'uniqueidentifier' => 'uuid',
        'sql_variant' => 'unknown',
    ];

    /** @var array<string, string> */
    private const ORACLE_TYPES = [
        // numerics
        'number' => 'decimal',
        'decimal' => 'decimal',
        'numeric' => 'decimal',
        'dec' => 'decimal',
        'float' => 'float',
        'binary_float' => 'float',
        'binary_double' => 'float',
        // strings
        'varchar2' => 'string',
        'nvarchar2' => 'string',
        'varchar' => 'string',
        'nvarchar' => 'string',
        'char' => 'string',
        'nchar' => 'string',
        'rowid' => 'string',
        'urowid' => 'string',
        // large text
        'clob' => 'text',
        'nclob' => 'text',
        'long' => 'text',
        // binary
        'blob' => 'blob',
        'bfile' => 'blob',
        'raw' => 'binary',
        'long raw' => 'binary',
        // temporal — Oracle `DATE` carries a time-of-day component.
        'date' => 'datetime',
        'timestamp' => 'timestamp',
        'timestamp with time zone' => 'timestamp',
        'timestamp with local time zone' => 'timestamp',
        // intervals
        'interval' => 'interval',
        'interval year to month' => 'interval',
        'interval day to second' => 'interval',
        // documents
        'xmltype' => 'json',
        'json' => 'json',
    ];

    /** @var array<string, string> */
    private const FIREBIRD_TYPES = [
        // integers
        'smallint' => 'smallint',
        'integer' => 'integer',
        'bigint' => 'bigint',
        'int128' => 'bigint',
        // numerics
        'numeric' => 'decimal',
        'decimal' => 'decimal',
        'float' => 'float',
        'double precision' => 'float',
        // strings
        'char' => 'string',
        'varchar' => 'string',
        // temporal
        'date' => 'date',
        'time' => 'time',
        'timestamp' => 'timestamp',
        // booleans
        'boolean' => 'boolean',
    ];

    /** @var array<string, string> */
    private const DB2_TYPES = [
        // integers
        'smallint' => 'smallint',
        'integer' => 'integer',
        'int' => 'integer',
        'bigint' => 'bigint',
        // numerics
        'decimal' => 'decimal',
        'numeric' => 'decimal',
        'decfloat' => 'decimal',
        'real' => 'float',
        'double' => 'float',
        'double precision' => 'float',
        'float' => 'float',
        // strings
        'char' => 'string',
        'varchar' => 'string',
        'graphic' => 'string',
        'vargraphic' => 'string',
        // large text
        'clob' => 'text',
        'dbclob' => 'text',
        'long varchar' => 'text',
        // binary
        'binary' => 'binary',
        'varbinary' => 'binary',
        'blob' => 'blob',
        // temporal
        'date' => 'date',
        'time' => 'time',
        'timestamp' => 'timestamp',
        // documents / booleans
        'xml' => 'text',
        'boolean' => 'boolean',
    ];

    /** @var array<string, string> */
    private const DUCKDB_TYPES = [
        // integers wider than the PostgreSQL vocabulary
        'hugeint' => 'bigint',
        'uhugeint' => 'bigint',
        'ubigint' => 'bigint',
        'utinyint' => 'smallint',
        'usmallint' => 'smallint',
        'uinteger' => 'integer',
        // unsigned aliases
        'uint8' => 'smallint',
        'uint16' => 'smallint',
        'uint32' => 'integer',
        'uint64' => 'bigint',
        // temporal variants
        'timestamp with time zone' => 'timestamp',
        'timestamptz' => 'timestamp',
        'timestamp_ns' => 'timestamp',
        'timestamp_ms' => 'timestamp',
        'timestamp_s' => 'timestamp',
        'timetz' => 'time',
        // binary — DuckDB spells the PostgreSQL `bytea` as `BLOB`.
        'blob' => 'binary',
        'bytea' => 'binary',
        // strings / documents
        'bit' => 'string',
        'varchar' => 'string',
        'json' => 'json',
    ];

    /** @var array<string, string> */
    private const SNOWFLAKE_TYPES = [
        // numerics — Snowflake folds INTEGER/BIGINT into NUMBER.
        'number' => 'decimal',
        'decimal' => 'decimal',
        'numeric' => 'decimal',
        'int' => 'decimal',
        'integer' => 'decimal',
        'bigint' => 'decimal',
        'smallint' => 'decimal',
        'tinyint' => 'decimal',
        'float' => 'float',
        'float4' => 'float',
        'float8' => 'float',
        'double' => 'float',
        'double precision' => 'float',
        'real' => 'float',
        // strings
        'varchar' => 'string',
        'char' => 'string',
        'character' => 'string',
        'string' => 'string',
        'text' => 'text',
        // binary
        'binary' => 'binary',
        'varbinary' => 'binary',
        // booleans
        'boolean' => 'boolean',
        'bool' => 'boolean',
        // temporal
        'date' => 'date',
        'time' => 'time',
        'timestamp' => 'timestamp',
        'timestamp_ntz' => 'timestamp',
        'timestamp_ltz' => 'timestamp',
        'timestamp_tz' => 'timestamp',
        'datetime' => 'timestamp',
        // semi-structured
        'variant' => 'json',
        'object' => 'json',
        'array' => 'json',
        // geospatial has no canonical equivalent
        'geography' => 'unknown',
        'geometry' => 'unknown',
    ];

    /** @var array<string, string> */
    private const BIGQUERY_TYPES = [
        // integers
        'int64' => 'bigint',
        'integer' => 'bigint',
        // numerics
        'float64' => 'float',
        'float' => 'float',
        'numeric' => 'decimal',
        'bignumeric' => 'decimal',
        // booleans
        'bool' => 'boolean',
        'boolean' => 'boolean',
        // strings
        'string' => 'string',
        'bytes' => 'binary',
        // temporal
        'date' => 'date',
        'time' => 'time',
        'datetime' => 'datetime',
        'timestamp' => 'timestamp',
        // documents
        'json' => 'json',
        'array' => 'json',
        'struct' => 'json',
        'record' => 'json',
        // geospatial has no canonical equivalent
        'geography' => 'unknown',
    ];

    /** @var array<string, string> */
    private const TRINO_TYPES = [
        // booleans
        'boolean' => 'boolean',
        'bool' => 'boolean',
        // integers
        'tinyint' => 'smallint',
        'smallint' => 'smallint',
        'integer' => 'integer',
        'int' => 'integer',
        'bigint' => 'bigint',
        // numerics
        'real' => 'float',
        'double' => 'float',
        'decimal' => 'decimal',
        // strings
        'varchar' => 'string',
        'char' => 'string',
        // binary
        'varbinary' => 'binary',
        // documents / identifiers
        'json' => 'json',
        'uuid' => 'uuid',
        'ipaddress' => 'string',
        'ipprefix' => 'string',
        // temporal
        'date' => 'date',
        'time' => 'time',
        'time with time zone' => 'time',
        'timestamp' => 'timestamp',
        'timestamp with time zone' => 'timestamp',
        // intervals
        'interval' => 'interval',
        'interval year to month' => 'interval',
        'interval day to second' => 'interval',
    ];

    /** @var array<string, string> */
    private const HANA_TYPES = [
        // integers
        'tinyint' => 'smallint',
        'smallint' => 'smallint',
        'integer' => 'integer',
        'int' => 'integer',
        'bigint' => 'bigint',
        // numerics
        'decimal' => 'decimal',
        'smalldecimal' => 'decimal',
        'real' => 'float',
        'double' => 'float',
        // strings
        'char' => 'string',
        'nchar' => 'string',
        'varchar' => 'string',
        'nvarchar' => 'string',
        'alphanum' => 'string',
        // large text
        'shorttext' => 'text',
        'text' => 'text',
        'clob' => 'text',
        'nclob' => 'text',
        // binary
        'binary' => 'binary',
        'varbinary' => 'binary',
        'blob' => 'blob',
        // temporal
        'date' => 'date',
        'time' => 'time',
        'seconddate' => 'datetime',
        'datetime' => 'datetime',
        'timestamp' => 'timestamp',
        // booleans / documents
        'boolean' => 'boolean',
        'st_geometry' => 'unknown',
    ];

    /**
     * Maps an engine-native type using the map for that engine.
     */
    public static function map(string $engine, string $raw): string
    {
        return match ($engine) {
            'sqlite', 'libsql', 'd1' => self::mapSqlite($raw),
            'postgresql' => self::mapPostgres($raw),
            'clickhouse' => self::mapClickhouse($raw),
            'sqlserver' => self::mapSqlServer($raw),
            'oracle' => self::mapOracle($raw),
            'firebird' => self::mapFirebird($raw),
            'db2' => self::mapDb2($raw),
            'duckdb' => self::mapDuckDb($raw),
            'snowflake' => self::mapSnowflake($raw),
            'bigquery' => self::mapBigQuery($raw),
            'trino' => self::mapTrino($raw),
            'hana' => self::mapHana($raw),
            default => self::mapMysql($raw),
        };
    }

    /**
     * Maps a MySQL/MariaDB native type to the canonical vocabulary. `tinyint(1)`
     * is the conventional boolean and is mapped to `boolean` explicitly.
     */
    public static function mapMysql(string $raw): string
    {
        $normalized = self::normalize($raw);

        if ($normalized === 'tinyint' && preg_match('/^tinyint\s*\(\s*1\s*\)/i', trim($raw)) === 1) {
            return 'boolean';
        }

        return self::MYSQL_TYPES[$normalized] ?? 'unknown';
    }

    /**
     * Maps a SQLite declared type to the canonical vocabulary. SQLite uses
     * dynamic typing with five storage classes; the declared type is reduced to
     * its affinity first, then refined when the declaration names a more
     * specific type (`DATE`, `DATETIME`, `BOOLEAN`, `JSON`, `UUID`).
     */
    public static function mapSqlite(string $raw): string
    {
        $normalized = self::normalize($raw);
        if ($normalized === '') {
            return 'blob';
        }

        if (preg_match('/\b(uuid|guid)\b/', $normalized) === 1) {
            return 'uuid';
        }
        if (preg_match('/\b(json|jsonb)\b/', $normalized) === 1) {
            return 'json';
        }
        if (preg_match('/\b(bool|boolean)\b/', $normalized) === 1) {
            return 'boolean';
        }
        if (preg_match('/\b(timestamp|datetime)\b/', $normalized) === 1) {
            return 'datetime';
        }
        if (preg_match('/\bdate\b/', $normalized) === 1) {
            return 'date';
        }
        if (preg_match('/\btime\b/', $normalized) === 1) {
            return 'time';
        }

        // SQLite affinity rules (https://sqlite.org/datatype3.html#affname).
        if (str_contains($normalized, 'int')) {
            return 'integer';
        }
        if (preg_match('/(char|clob|text)/', $normalized) === 1) {
            return 'text';
        }
        if (str_contains($normalized, 'blob')) {
            return 'blob';
        }
        if (preg_match('/(real|floa|doub)/', $normalized) === 1) {
            return 'float';
        }

        return 'decimal'; // NUMERIC affinity
    }

    /**
     * Maps a PostgreSQL type (as reported by `format_type`) to the canonical
     * vocabulary. Variants such as `timestamp with time zone` collapse to the
     * same storage class, and arrays are mapped to `json`.
     */
    public static function mapPostgres(string $raw): string
    {
        $normalized = self::normalize($raw);
        if (str_ends_with($normalized, '[]')) {
            return 'json';
        }
        if ($normalized === 'timestamp with time zone' || $normalized === 'timestamp without time zone') {
            return 'timestamp';
        }
        if ($normalized === 'time with time zone' || $normalized === 'time without time zone') {
            return 'time';
        }

        return self::POSTGRES_TYPES[$normalized] ?? 'unknown';
    }

    /**
     * Maps a ClickHouse type (as reported by `system.columns.type`) to the
     * canonical vocabulary. The type string carries wrappers inline, so
     * `Nullable(T)` and `LowCardinality(T)` are unwrapped before the lookup, and
     * the composite types (`Array`, `Map`, `Tuple`, `Nested`) collapse to `json`.
     */
    public static function mapClickhouse(string $raw): string
    {
        $nullable = self::unwrap($raw, 'Nullable');
        if ($nullable !== null) {
            return self::mapClickhouse($nullable);
        }
        $lowCardinality = self::unwrap($raw, 'LowCardinality');
        if ($lowCardinality !== null) {
            return self::mapClickhouse($lowCardinality);
        }

        $normalized = self::normalize($raw);
        if (preg_match('/^(array|map|tuple|nested)\b/', $normalized) === 1) {
            return 'json';
        }

        return self::CLICKHOUSE_TYPES[$normalized] ?? 'unknown';
    }

    /**
     * Maps a SQL Server native type to the canonical vocabulary. Two quirks
     * matter: `timestamp` is an 8-byte rowversion and therefore binary, not
     * temporal, and `uniqueidentifier` is the engine's UUID spelling.
     */
    public static function mapSqlServer(string $raw): string
    {
        return self::SQLSERVER_TYPES[self::normalize($raw)] ?? 'unknown';
    }

    /**
     * Maps an Oracle native type to the canonical vocabulary.
     *
     * `NUMBER` is a single arbitrary-precision type used for both fractional
     * and integral columns, so the declared precision is inspected: an unscaled
     * `NUMBER(p)` with `p <= 4` is an integer surrogate (else `bigint`), while
     * bare `NUMBER` and `NUMBER(p, s)` stay `decimal` to preserve exactness.
     * Oracle `DATE` stores a time component, so it maps to `datetime`.
     */
    public static function mapOracle(string $raw): string
    {
        $normalized = self::normalize($raw);
        if ($normalized === 'number') {
            $declared = preg_match('/^number\s*\(\s*(\d+)\s*\)/i', trim($raw), $match);
            if ($declared === 1) {
                return (int) $match[1] <= 4 ? 'integer' : 'bigint';
            }
        }

        return self::ORACLE_TYPES[$normalized] ?? 'unknown';
    }

    /**
     * Maps a Firebird native type to the canonical vocabulary. `BLOB` is split
     * by its declared sub-type: sub-type 1 (TEXT) and character-set text blobs
     * map to `text`, everything else (sub-type 0, BINARY) maps to `binary`.
     * `INT128` is Firebird 4's 128-bit integer and collapses to `bigint`.
     */
    public static function mapFirebird(string $raw): string
    {
        $normalized = self::normalize($raw);
        if ($normalized === 'blob' || str_starts_with($normalized, 'blob ')) {
            // Sub-type 1 is the TEXT blob; a declared character set also implies text.
            return preg_match('/\bsub_type\s*1(?:\D|$)|\btext\b/', $normalized) === 1
                ? 'text'
                : 'binary';
        }

        return self::FIREBIRD_TYPES[$normalized] ?? 'unknown';
    }

    /**
     * Maps a Db2 native type to the canonical vocabulary. Db2 keeps the SQL
     * `GRAPHIC`/`VARGRAPHIC` double-byte strings (mapped to `string`), the
     * `DBCLOB` double-byte LOB (mapped to `text`) and treats `XML` as text.
     */
    public static function mapDb2(string $raw): string
    {
        return self::DB2_TYPES[self::normalize($raw)] ?? 'unknown';
    }

    /**
     * Maps a DuckDB native type to the canonical vocabulary.
     *
     * DuckDB is PostgreSQL-flavoured, so {@see mapPostgres} is the base and
     * only DuckDB-specific spellings are layered on top: unsigned and 128-bit
     * integers (`UBIGINT`, `HUGEINT`, …), the timestamp precision variants
     * (`TIMESTAMP_NS`/`_MS`/`_S`, `TIMESTAMPTZ`), `BIT` strings, and the nested
     * types (`STRUCT`, `MAP`, `LIST`, `UNION`, `T[]`), which collapse to `json`
     * exactly like PostgreSQL arrays.
     */
    public static function mapDuckDb(string $raw): string
    {
        $normalized = self::normalize($raw);
        if (preg_match('/^(struct|map|list|union)\b/', $normalized) === 1) {
            return 'json';
        }
        if (str_ends_with($normalized, '[]')) {
            return 'json';
        }

        return self::DUCKDB_TYPES[$normalized] ?? self::mapPostgres($raw);
    }

    /**
     * Maps a Snowflake native type to the canonical vocabulary. Snowflake has a
     * single `NUMBER` family for every exact numeric (including `INTEGER`/
     * `BIGINT` spellings), so those collapse to `decimal`; the three timestamp
     * flavours (`NTZ`/`LTZ`/`TZ`) all map to `timestamp`, and the
     * semi-structured `VARIANT`/`OBJECT`/`ARRAY` types to `json`.
     */
    public static function mapSnowflake(string $raw): string
    {
        return self::SNOWFLAKE_TYPES[self::normalize($raw)] ?? 'unknown';
    }

    /**
     * Maps a BigQuery native type to the canonical vocabulary. BigQuery only has
     * 64-bit integers (`INT64` → `bigint`), and its nested `ARRAY`/`STRUCT`/
     * `RECORD` types collapse to `json`. BigQuery declares parameters with angle
     * brackets (`ARRAY<INT64>`, `STRUCT<a INT64>`), which `normalize` does not
     * strip, so the generic argument list is removed before the lookup.
     */
    public static function mapBigQuery(string $raw): string
    {
        $stripped = (string) preg_replace('/<[^>]*>/', ' ', $raw);

        return self::BIGQUERY_TYPES[self::normalize($stripped)] ?? 'unknown';
    }

    /**
     * Maps a Trino native type to the canonical vocabulary. Trino spells its
     * parameterised temporal types with the modifiers after the precision
     * (`timestamp(3) with time zone`), which normalisation collapses to the same
     * `timestamp with time zone` key; `ARRAY`/`MAP`/`ROW` collapse to `json` and
     * `IPADDRESS`/`IPPREFIX` are kept as strings.
     */
    public static function mapTrino(string $raw): string
    {
        $normalized = self::normalize($raw);
        if (preg_match('/^(array|map|row)\b/', $normalized) === 1) {
            return 'json';
        }

        return self::TRINO_TYPES[$normalized] ?? 'unknown';
    }

    /**
     * Maps a SAP HANA native type to the canonical vocabulary. `SECONDDATE` is
     * HANA's second-precision timestamp (mapped to `datetime`), `ALPHANUM` is a
     * character type, `ST_GEOMETRY` has no canonical equivalent, and the
     * parameterised `ARRAY(...)` column type collapses to `json`.
     */
    public static function mapHana(string $raw): string
    {
        $normalized = self::normalize($raw);
        if ($normalized === 'array' || str_starts_with($normalized, 'array ')) {
            return 'json';
        }

        return self::HANA_TYPES[$normalized] ?? 'unknown';
    }

    /**
     * Returns the inner type when `$raw` is exactly `Wrapper(...)`, else null.
     */
    private static function unwrap(string $raw, string $wrapper): ?string
    {
        $trimmed = trim($raw);
        $prefix = $wrapper . '(';
        if (str_starts_with(strtolower($trimmed), strtolower($prefix)) && str_ends_with($trimmed, ')')) {
            return substr($trimmed, strlen($prefix), -1);
        }

        return null;
    }

    /**
     * Lowercases, trims and strips every `(params)` group (length, precision or
     * modifiers such as `(3)`) from a declared type.
     */
    private static function normalize(string $raw): string
    {
        $normalized = strtolower(trim($raw));
        $normalized = (string) preg_replace('/\(.*?\)/', ' ', $normalized);
        $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }
}
