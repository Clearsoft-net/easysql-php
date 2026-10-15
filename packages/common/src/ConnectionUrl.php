<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Common;

use InvalidArgumentException;

/**
 * Connection-string parsing for the connector engines.
 *
 * Supports the conventional URL/DSN of each engine, e.g.:
 *   mysql://user:pass@host:3306/db
 *   mariadb://user:pass@host:3306/db
 *   postgresql://user:pass@host:5432/db
 *   clickhouse://user:pass@host:8123/db       (HTTP interface)
 *   clickhouses://user:pass@host:8443/db      (HTTPS interface)
 *   sqlite:///absolute/path/to.db
 *
 * Mirrors `url.ts` of the TypeScript sibling package. The returned password is
 * held in memory only; callers decide whether it is ever persisted (it should
 * not be).
 */
final class ConnectionUrl
{
    /** Conventional port per engine; file engines use 0. */
    private const DEFAULT_PORTS = [
        'mysql' => 3306,
        'mariadb' => 3306,
        'postgresql' => 5432,
        'clickhouse' => 8123,
        'sqlite' => 0,
        'sqlserver' => 1433,
        'oracle' => 1521,
        'firebird' => 3050,
        'db2' => 50000,
        'duckdb' => 0,
        'libsql' => 0,
        'd1' => 0,
        'snowflake' => 443,
        'bigquery' => 443,
        'trino' => 8080,
        'hana' => 30015,
    ];

    /** URL schemes accepted, and the engine each maps to. */
    private const PROTOCOLS = [
        'mysql' => 'mysql',
        'mariadb' => 'mariadb',
        'postgres' => 'postgresql',
        'postgresql' => 'postgresql',
        'clickhouse' => 'clickhouse',
        'clickhouses' => 'clickhouse',
        'sqlserver' => 'sqlserver',
        'mssql' => 'sqlserver',
        'oracle' => 'oracle',
        'firebird' => 'firebird',
        'db2' => 'db2',
        'duckdb' => 'duckdb',
        'libsql' => 'libsql',
        'turso' => 'libsql',
        'd1' => 'd1',
        'snowflake' => 'snowflake',
        'bigquery' => 'bigquery',
        'trino' => 'trino',
        'hana' => 'hana',
    ];

    /** Engines whose target is a filesystem path rather than a host. */
    private const FILE_ENGINES = ['sqlite', 'duckdb'];

    /** Engines that identify their target without user credentials. */
    private const NO_USER_ENGINES = ['bigquery', 'libsql', 'd1'];

    /** Engines where a database (or service/namespace) in the path is mandatory. */
    private const REQUIRES_DATABASE = [
        'mysql',
        'mariadb',
        'postgresql',
        'clickhouse',
        'sqlserver',
        'db2',
        'firebird',
        'oracle',
        'snowflake',
    ];

    /**
     * Parses a connection URL into its parts.
     *
     * @return array{type: string, host: string, port: int, user: string, password: string, database: string, ssl: bool}
     *
     * @throws InvalidArgumentException On an unsupported scheme or a missing part.
     */
    public static function parse(string $raw): array
    {
        $trimmed = trim($raw);

        if (preg_match('/^([a-z0-9+.-]+):/i', $trimmed, $match) !== 1) {
            throw new InvalidArgumentException("Unsupported connection URL: '{$raw}'.");
        }

        $scheme = strtolower($match[1]);

        if (in_array($scheme, self::FILE_ENGINES, true)) {
            return self::parseFilePath($trimmed, $scheme);
        }

        if (!isset(self::PROTOCOLS[$scheme])) {
            throw new InvalidArgumentException(
                "Unsupported protocol '{$scheme}'. Use mysql://, postgresql://, clickhouse://, "
                . 'sqlite:///path/to.db, or another supported engine URL.',
            );
        }

        $type = self::PROTOCOLS[$scheme];

        $parts = parse_url($trimmed);
        if ($parts === false) {
            throw new InvalidArgumentException("Invalid connection URL: '{$raw}'.");
        }

        $host = ($parts['host'] ?? '') !== '' ? $parts['host'] : '127.0.0.1';
        $defaultPort = $scheme === 'clickhouses' ? 8443 : self::DEFAULT_PORTS[$type];
        $port = isset($parts['port']) ? (int) $parts['port'] : $defaultPort;
        $user = rawurldecode($parts['user'] ?? '');
        $password = rawurldecode($parts['pass'] ?? '');
        $database = ltrim($parts['path'] ?? '', '/');

        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        // ClickHouse uses the HTTP interface: 8123 plain / 8443 TLS.
        // `clickhouses://` implies TLS; `ssl=true` / `secure=true` /
        // `sslmode=require` force it on.
        $ssl = $scheme === 'clickhouses'
            || ($query['ssl'] ?? '') === 'true'
            || ($query['secure'] ?? '') === 'true'
            || ($query['sslmode'] ?? '') === 'require';

        if (!in_array($type, self::NO_USER_ENGINES, true) && $user === '') {
            throw new InvalidArgumentException('Missing database user in connection URL.');
        }

        if (in_array($type, self::REQUIRES_DATABASE, true) && $database === '') {
            throw new InvalidArgumentException('Missing database name in connection URL.');
        }

        return [
            'type' => $type,
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $password,
            'database' => $database,
            'ssl' => $ssl,
        ];
    }

    /**
     * Merges partial overrides onto a base connection, applying defaults.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overrides
     * @return array{type: string, host: string, port: int, user: string, password: string, database: string, ssl: bool}
     *
     * @throws InvalidArgumentException When a required part is missing.
     */
    public static function merge(array $base, array $overrides): array
    {
        $type = $overrides['type'] ?? $base['type'] ?? 'mysql';

        if (in_array($type, self::FILE_ENGINES, true)) {
            $database = $overrides['database'] ?? $base['database'] ?? null;
            if (!is_string($database) || $database === '') {
                throw new InvalidArgumentException(ucfirst($type) . ' file path is required.');
            }

            return [
                'type' => $type,
                'host' => '',
                'port' => 0,
                'user' => '',
                'password' => '',
                'database' => $database,
                'ssl' => false,
            ];
        }

        $host = $overrides['host'] ?? $base['host'] ?? '127.0.0.1';
        $port = $overrides['port'] ?? $base['port'] ?? self::DEFAULT_PORTS[$type];
        $user = $overrides['user'] ?? $base['user'] ?? '';
        $database = $overrides['database'] ?? $base['database'] ?? '';

        if (!in_array($type, self::NO_USER_ENGINES, true) && $user === '') {
            throw new InvalidArgumentException('Database user is required.');
        }

        if (in_array($type, self::REQUIRES_DATABASE, true) && $database === '') {
            throw new InvalidArgumentException('Database name is required.');
        }

        return [
            'type' => $type,
            'host' => $host,
            'port' => (int) $port,
            'user' => $user,
            'password' => $overrides['password'] ?? $base['password'] ?? '',
            'database' => $database,
            'ssl' => $overrides['ssl'] ?? $base['ssl'] ?? false,
        ];
    }

    /**
     * @return array{type: string, host: string, port: int, user: string, password: string, database: string, ssl: bool}
     */
    private static function parseFilePath(string $raw, string $type): array
    {
        $file = (string) preg_replace('/^' . preg_quote($type, '/') . ':\/\//', '', trim($raw));

        if (str_starts_with($file, 'localhost/')) {
            $file = substr($file, strlen('localhost'));
        }
        if (str_ends_with($file, '/') && strlen($file) > 1) {
            $file = substr($file, 0, -1);
        }
        if ($file === '') {
            throw new InvalidArgumentException(
                "{$type} URL must include a file path (e.g. {$type}:///tmp/db.db).",
            );
        }

        return [
            'type' => $type,
            'host' => '',
            'port' => 0,
            'user' => '',
            'password' => '',
            'database' => $file,
            'ssl' => false,
        ];
    }
}
