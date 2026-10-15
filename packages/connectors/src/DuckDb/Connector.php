<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\DuckDb;

use Clearsoft\EasySQL\Connectors\BaseConnector;
use PDO;
use Throwable;

/**
 * DuckDB connector — local schema introspection and SELECT execution.
 *
 * The database is opened with the `pdo_duckdb` driver and released when the
 * connector closes. No global state: several databases can be open at once.
 */
final class Connector extends BaseConnector
{
    public function __construct(private readonly ConnectionConfig $config)
    {
        parent::__construct();
    }

    protected function engine(): string
    {
        return 'DuckDB';
    }

    protected function openHandle(): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => (int) ceil($this->config->timeout),
        ];

        return new PDO($this->config->toDsn(), null, null, $options);
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
     * package: ['engine' => 'duckdb', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $handle = $this->requireHandle();
        assert($handle instanceof PDO);

        try {
            $tableStmt = $handle->prepare(Queries::TABLES);
            $tableStmt->execute();
            $tableRows = $tableStmt->fetchAll();

            $constraintStmt = $handle->prepare(Queries::CONSTRAINTS);
            $constraintStmt->execute();
            $constraintRows = $constraintStmt->fetchAll();

            $primaryKeys = [];
            $foreignKeys = [];
            foreach ($constraintRows as $constraint) {
                $table = (string) $constraint['table_name'];
                $columns = $this->toStringList($constraint['constraint_column_names']);

                if ($constraint['constraint_type'] === 'PRIMARY KEY') {
                    $primaryKeys[$table] = array_fill_keys($columns, true);
                    continue;
                }

                $referencedTable = $constraint['referenced_table'];
                if ($constraint['constraint_type'] !== 'FOREIGN KEY' || $referencedTable === null) {
                    continue;
                }

                $referenced = $this->toStringList($constraint['referenced_column_names']);
                foreach ($columns as $index => $column) {
                    $referencedColumn = $referenced[$index] ?? null;
                    if ($referencedColumn !== null) {
                        $foreignKeys[$table][$column] = [
                            'table' => (string) $referencedTable,
                            'column' => $referencedColumn,
                        ];
                    }
                }
            }

            $columnStmt = $handle->prepare(Queries::COLUMNS);
            $tables = [];
            foreach ($tableRows as $table) {
                $name = (string) $table['table_name'];
                $columnStmt->execute([$name]);

                $columns = [];
                foreach ($columnStmt->fetchAll() as $column) {
                    $columnName = (string) $column['column_name'];
                    $primaryKey = isset($primaryKeys[$name][$columnName]);

                    $columns[] = [
                        'name' => $columnName,
                        'dataType' => $column['data_type'],
                        // A primary key is never nullable; DuckDB reports NOT
                        // NULL separately, but the constraint is authoritative.
                        'nullable' => $primaryKey ? false : $this->toBool($column['is_nullable']),
                        'primaryKey' => $primaryKey,
                        'defaultValue' => $column['column_default'],
                        'foreignKey' => $foreignKeys[$name][$columnName] ?? null,
                        'ordinal' => (int) $column['column_index'],
                    ];
                }

                $tables[] = [
                    'name' => $name,
                    'columns' => $columns,
                    'rowsApprox' => $this->toNullableInt($table['estimated_size']),
                ];
            }

            return ['engine' => 'duckdb', 'tables' => $tables];
        } catch (Throwable $e) {
            throw $this->wrapIntrospection($e);
        }
    }

    /**
     * Normalizes a DuckDB list value (`['a', 'b']` or `"[a, b]"`) into a list
     * of strings.
     *
     * @return list<string>
     */
    private function toStringList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map(
                static fn (mixed $item): string => is_scalar($item) ? (string) $item : '',
                $value,
            ));
        }

        if (!is_string($value)) {
            return [];
        }

        $trimmed = trim($value);
        if (!str_starts_with($trimmed, '[') || !str_ends_with($trimmed, ']')) {
            return $trimmed === '' ? [] : [$trimmed];
        }

        $inner = trim(substr($trimmed, 1, -1));
        if ($inner === '') {
            return [];
        }

        return array_map(
            static fn (string $item): string => trim($item, " '\""),
            explode(',', $inner),
        );
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        return in_array($value, ['t', 'true', '1', 'y', 'yes'], true);
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
