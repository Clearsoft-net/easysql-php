<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Postgres;

use Clearsoft\EasySQL\Common\SqlValidator;
use PDO;
use PDOException;
use RuntimeException;

/**
 * PostgreSQL connector — local schema introspection and SELECT execution.
 *
 * The customer database is introspected locally and only the schema reaches
 * the API, which never stores credentials or executes SQL.
 *
 * No global state: several connections can be open at the same time.
 */
final class Connector
{
    private ?PDO $pdo = null;

    public function __construct(private readonly ConnectionConfig $config)
    {
    }

    /**
     * Open the connection. Idempotent — calling twice keeps the same connection.
     *
     * @throws ConnectorException When the connection cannot be established.
     */
    public function connect(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => (int) ceil($this->config->timeout),
        ];

        try {
            $this->pdo = new PDO(
                $this->config->toDsn(),
                $this->config->user,
                $this->config->password,
                $options,
            );
        } catch (PDOException $e) {
            throw ConnectorException::connectionFailed($e);
        }
    }

    /**
     * Close the connection and release all resources.
     */
    public function close(): void
    {
        $this->pdo = null;
    }

    /**
     * Introspect the configured schemas.
     *
     * Returns the raw introspection shape consumed by the schema-generation
     * package: ['tables' => [['name', 'columns' => [...], 'rows_approx']]].
     *
     * @throws ConnectorException When introspection fails.
     */
    public function introspect(): array
    {
        $pdo = $this->requireConnection();

        try {
            $tablesStmt = $pdo->prepare(
                "SELECT c.relname AS table_name, c.reltuples::bigint AS rows_approx
                 FROM pg_class c
                 JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = :schema
                   AND c.relkind = 'r'
                   AND NOT c.relispartition
                 ORDER BY c.relname",
            );

            $columnsStmt = $pdo->prepare(
                "SELECT a.attname AS column_name,
                        format_type(a.atttypid, a.atttypmod) AS data_type,
                        NOT a.attnotnull AS is_nullable,
                        pg_get_expr(d.adbin, d.adrelid) AS column_default,
                        EXISTS (
                            SELECT 1 FROM pg_index i
                            WHERE i.indrelid = c.oid
                              AND a.attnum = ANY(i.indkey)
                              AND i.indisprimary
                        ) AS is_primary,
                        ref.relname AS foreign_table,
                        ref_att.attname AS foreign_column
                 FROM pg_attribute a
                 JOIN pg_class c ON c.oid = a.attrelid
                 JOIN pg_namespace n ON n.oid = c.relnamespace
                 LEFT JOIN pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum
                 LEFT JOIN pg_constraint con
                        ON con.conrelid = c.oid
                       AND a.attnum = ANY(con.conkey)
                       AND con.contype = 'f'
                 LEFT JOIN pg_class ref ON ref.oid = con.confrelid
                 LEFT JOIN pg_attribute ref_att
                        ON ref_att.attrelid = ref.oid
                       AND ref_att.attnum = con.confkey[1]
                 WHERE n.nspname = :schema
                   AND c.relname = :table
                   AND a.attnum > 0
                   AND NOT a.attisdropped
                 ORDER BY a.attnum",
            );

            $tables = [];
            foreach ($this->config->schemas as $schema) {
                $tablesStmt->execute(['schema' => $schema]);

                foreach ($tablesStmt->fetchAll() as $table) {
                    $columnsStmt->execute([
                        'schema' => $schema,
                        'table' => $table['table_name'],
                    ]);

                    $columns = [];
                    foreach ($columnsStmt->fetchAll() as $column) {
                        $columns[] = [
                            'name' => $column['column_name'],
                            'type' => TypeMapper::map($column['data_type']),
                            'nullable' => $this->toBool($column['is_nullable']),
                            'primary_key' => $this->toBool($column['is_primary']),
                            'default' => $column['column_default'],
                            'foreign_key' => $column['foreign_table'] !== null
                                && $column['foreign_column'] !== null
                                ? [
                                    'table' => $column['foreign_table'],
                                    'column' => $column['foreign_column'],
                                ]
                                : null,
                        ];
                    }

                    $tables[] = [
                        'name' => $table['table_name'],
                        'columns' => $columns,
                        'rows_approx' => $table['rows_approx'] !== null
                            ? (int) $table['rows_approx']
                            : null,
                    ];
                }
            }

            return ['tables' => $tables];
        } catch (PDOException $e) {
            throw ConnectorException::introspectionFailed($e);
        }
    }

    /**
     * Execute a generated SELECT statement and return typed rows.
     *
     * @param array<string, mixed> $params Bound parameters for prepared statements.
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, row_count: int, duration_ms: int}
     * @throws ConnectorException When execution fails or the SQL is unsafe.
     */
    public function execute(string $sql, array $params = []): array
    {
        $pdo = $this->requireConnection();

        $validation = SqlValidator::validateSelectOnly($sql);
        if (!$validation['ok']) {
            throw new ConnectorException(
                'SQL safety check failed: ' . ($validation['reason'] ?? 'unknown'),
            );
        }

        $started = (int) (microtime(true) * 1000);

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw ConnectorException::executionFailed($e);
        }

        return [
            'columns' => array_keys($rows[0] ?? []),
            'rows' => $rows,
            'row_count' => count($rows),
            'duration_ms' => (int) (microtime(true) * 1000) - $started,
        ];
    }

    private function requireConnection(): PDO
    {
        if ($this->pdo === null) {
            throw new RuntimeException('Not connected — call connect() first.');
        }

        return $this->pdo;
    }

    /**
     * PDO pgsql returns booleans as PHP bool or as 't'/'f' strings depending
     * on the build; normalize both into a real bool.
     */
    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, ['t', 'true', '1', 1, 'y', 'yes'], true);
    }
}
