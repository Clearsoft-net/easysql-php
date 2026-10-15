<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\SQLite;

use Clearsoft\EasySQL\Connectors\BaseConnector;
use PDO;
use PDOException;
use Throwable;

/**
 * SQLite connector — local schema introspection and SELECT execution.
 *
 * Opens file-based and in-memory databases with an explicit read-only mode.
 * The database is opened per connection and no global state survives it.
 */
final class Connector extends BaseConnector
{
    public function __construct(private readonly ConnectionConfig $config)
    {
        parent::__construct();
    }

    protected function engine(): string
    {
        return 'SQLite';
    }

    protected function openHandle(): PDO
    {
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

        $pdo = new PDO($this->config->toDsn(), null, null, $options);

        if ($enforceQueryOnly) {
            $pdo->exec('PRAGMA query_only = 1');
        }

        return $pdo;
    }

    protected function closeHandle(mixed $handle): void
    {
        // PDO releases its connection when the reference is dropped.
    }

    protected function runQuery(mixed $handle, string $sql, array $params): array
    {
        assert($handle instanceof PDO);

        $stmt = $handle->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'columns' => $rows === [] ? [] : array_keys($rows[0]),
            'rows' => $rows,
        ];
    }

    /**
     * Introspect the database schema.
     *
     * Returns the raw introspection shape consumed by the schema-generation
     * package: ['engine' => 'sqlite', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $handle = $this->requireHandle();
        assert($handle instanceof PDO);

        try {
            $tableRows = $handle->query(Queries::TABLES)->fetchAll();

            $tables = [];
            foreach ($tableRows as $table) {
                $name = $table['name'];

                $fkByColumn = [];
                foreach ($handle->query(Queries::foreignKeyList($name))->fetchAll() as $fk) {
                    $fkByColumn[$fk['from']] = [
                        'table' => $fk['table'],
                        'column' => $fk['to'],
                    ];
                }

                $columns = [];
                foreach ($handle->query(Queries::tableInfo($name))->fetchAll() as $column) {
                    $pk = (int) $column['pk'];
                    $notNull = (int) $column['notnull'];
                    $columns[] = [
                        'name' => $column['name'],
                        'dataType' => $column['type'],
                        // A primary key is never nullable; PRAGMA reports notnull=0
                        // for an INTEGER PRIMARY KEY, so it is excluded explicitly.
                        'nullable' => $notNull === 0 && $pk === 0,
                        'primaryKey' => $pk > 0,
                        'defaultValue' => $column['dflt_value'],
                        'foreignKey' => $fkByColumn[$column['name']] ?? null,
                        'ordinal' => (int) $column['cid'],
                    ];
                }

                $tables[] = [
                    'name' => $name,
                    'columns' => $columns,
                    'rowsApprox' => $this->countRows($handle, $name),
                ];
            }

            return ['engine' => 'sqlite', 'tables' => $tables];
        } catch (Throwable $e) {
            throw $this->wrapIntrospection($e);
        }
    }

    private function countRows(PDO $handle, string $table): ?int
    {
        try {
            $row = $handle->query(Queries::rowCount($table))->fetch();

            return $row !== false ? (int) $row['c'] : null;
        } catch (PDOException) {
            return null;
        }
    }
}
