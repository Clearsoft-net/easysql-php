<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\SQLite;

use Clearsoft\EasySQL\Common\SqlValidator;
use PDO;
use PDOException;
use RuntimeException;

/**
 * SQLite connector — local schema introspection and SELECT execution.
 *
 * Opens file-based and in-memory databases with an explicit read-only mode.
 * The database is opened per connection and no global state survives it.
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
     * @throws ConnectorException When the database cannot be opened.
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

        $enforceQueryOnly = false;

        if ($this->config->readOnly) {
            // Prefer the SQLite open flag; fall back to PRAGMA query_only on
            // builds where the driver-specific constants are unavailable.
            if (defined('PDO::SQLITE_ATTR_OPEN_FLAGS') && defined('PDO::SQLITE_OPEN_READONLY')) {
                $options[PDO::SQLITE_ATTR_OPEN_FLAGS] = PDO::SQLITE_OPEN_READONLY;
            } else {
                $enforceQueryOnly = true;
            }
        }

        try {
            $this->pdo = new PDO($this->config->toDsn(), null, null, $options);
        } catch (PDOException $e) {
            throw ConnectorException::connectionFailed($e);
        }

        if ($enforceQueryOnly) {
            $this->pdo->exec('PRAGMA query_only = 1');
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
            $tablesStmt = $pdo->query(
                "SELECT name FROM sqlite_master
                 WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
                 ORDER BY name",
            );
            $tableRows = $tablesStmt->fetchAll();

            $tables = [];
            foreach ($tableRows as $table) {
                $name = $table['name'];

                $columnsStmt = $pdo->query(
                    'PRAGMA table_info(' . self::quoteIdent($name) . ')',
                );
                $fkStmt = $pdo->query(
                    'PRAGMA foreign_key_list(' . self::quoteIdent($name) . ')',
                );

                $fkByColumn = [];
                foreach ($fkStmt->fetchAll() as $fk) {
                    $fkByColumn[$fk['from']] = [
                        'table' => $fk['table'],
                        'column' => $fk['to'],
                    ];
                }

                $columns = [];
                foreach ($columnsStmt->fetchAll() as $column) {
                    $columns[] = [
                        'name' => $column['name'],
                        'type' => $column['type'] !== '' ? $column['type'] : 'BLOB',
                        'nullable' => (int) $column['notnull'] === 0,
                        'primary_key' => (int) $column['pk'] > 0,
                        'default' => $column['dflt_value'],
                        'foreign_key' => $fkByColumn[$column['name']] ?? null,
                    ];
                }

                $tables[] = [
                    'name' => $name,
                    'columns' => $columns,
                    'rows_approx' => $this->countRows($pdo, $name),
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

    private function countRows(PDO $pdo, string $table): ?int
    {
        try {
            $stmt = $pdo->query(
                'SELECT COUNT(*) AS c FROM ' . self::quoteIdent($table),
            );
            $row = $stmt->fetch();

            return $row !== false ? (int) $row['c'] : null;
        } catch (PDOException) {
            return null;
        }
    }

    /**
     * Quote a SQLite identifier with double-quotes (SQL standard).
     */
    private static function quoteIdent(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
