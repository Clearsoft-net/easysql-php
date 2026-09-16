<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\MySQL;

use Clearsoft\EasySQL\Common\SqlValidator;
use PDO;
use PDOException;
use RuntimeException;

/**
 * MySQL/MariaDB connector — local schema introspection and SELECT execution.
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

        if ($this->config->ssl) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

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
     * Introspect the database schema.
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
                "SELECT TABLE_NAME AS table_name, TABLE_ROWS AS table_rows
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = :schema AND TABLE_TYPE = 'BASE TABLE'
                 ORDER BY TABLE_NAME",
            );
            $tablesStmt->execute(['schema' => $this->config->database]);
            $tableRows = $tablesStmt->fetchAll();

            $columnsStmt = $pdo->prepare(
                "SELECT c.COLUMN_NAME AS column_name, c.DATA_TYPE AS data_type,
                        c.IS_NULLABLE AS is_nullable, c.COLUMN_DEFAULT AS column_default,
                        c.COLUMN_KEY AS column_key,
                        k.REFERENCED_TABLE_NAME AS referenced_table_name,
                        k.REFERENCED_COLUMN_NAME AS referenced_column_name
                 FROM information_schema.COLUMNS c
                 LEFT JOIN information_schema.KEY_COLUMN_USAGE k
                        ON k.TABLE_SCHEMA = c.TABLE_SCHEMA
                       AND k.TABLE_NAME = c.TABLE_NAME
                       AND k.COLUMN_NAME = c.COLUMN_NAME
                       AND k.REFERENCED_TABLE_NAME IS NOT NULL
                 WHERE c.TABLE_SCHEMA = :schema AND c.TABLE_NAME = :table
                 ORDER BY c.ORDINAL_POSITION",
            );

            $tables = [];
            foreach ($tableRows as $table) {
                $columnsStmt->execute([
                    'schema' => $this->config->database,
                    'table' => $table['table_name'],
                ]);

                $columns = [];
                foreach ($columnsStmt->fetchAll() as $column) {
                    $columns[] = [
                        'name' => $column['column_name'],
                        'type' => $column['data_type'],
                        'nullable' => $column['is_nullable'] === 'YES',
                        'primary_key' => $column['column_key'] === 'PRI',
                        'default' => $column['column_default'],
                        'foreign_key' => $column['referenced_table_name'] !== null
                            && $column['referenced_column_name'] !== null
                            ? [
                                'table' => $column['referenced_table_name'],
                                'column' => $column['referenced_column_name'],
                            ]
                            : null,
                    ];
                }

                $tables[] = [
                    'name' => $table['table_name'],
                    'columns' => $columns,
                    'rows_approx' => $table['table_rows'] !== null
                        ? (int) $table['table_rows']
                        : null,
                ];
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
}
