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

    /**
     * Maps an engine-native type using the map for that engine.
     */
    public static function map(string $engine, string $raw): string
    {
        return match ($engine) {
            'sqlite' => self::mapSqlite($raw),
            'postgresql' => self::mapPostgres($raw),
            'clickhouse' => self::mapClickhouse($raw),
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
