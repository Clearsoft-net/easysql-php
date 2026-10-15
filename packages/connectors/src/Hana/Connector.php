<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Hana;

use Clearsoft\EasySQL\Connectors\BaseConnector;
use Clearsoft\EasySQL\Connectors\Helpers;
use PDO;
use Throwable;

/**
 * SAP HANA connector — local schema introspection and SELECT execution.
 *
 * Metadata is read from the `SYS` catalog in a fixed number of batched queries,
 * so a schema with N tables costs a handful of round trips rather than one per
 * table. The connection is opened explicitly and released on close.
 */
final class Connector extends BaseConnector
{
    public function __construct(private readonly ConnectionConfig $config)
    {
        parent::__construct(array_filter(
            [$config->password],
            static fn (string $secret): bool => $secret !== '',
        ));
    }

    protected function engine(): string
    {
        return 'SAP HANA';
    }

    protected function openHandle(): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => (int) ceil($this->config->timeout),
        ];

        return new PDO(
            $this->config->toDsn(),
            $this->config->user,
            $this->config->password,
            $options,
        );
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
     * Introspect the current schema.
     *
     * Returns the raw introspection shape consumed by the schema-generation
     * package: ['engine' => 'hana', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $handle = $this->requireHandle();
        assert($handle instanceof PDO);

        try {
            $tableRows = $this->fetch($handle, Queries::TABLES);
            $columnRows = $this->fetch($handle, Queries::COLUMNS);
            $primaryKeyRows = $this->fetch($handle, Queries::PRIMARY_KEYS);
            $foreignKeyRows = $this->fetch($handle, Queries::FOREIGN_KEYS);
            $rowCounts = $this->loadRowCounts($handle);

            $columnsByTable = [];
            foreach ($columnRows as $row) {
                $row = (array) $row;
                $columnsByTable[(string) $row['table_name']][] = $row;
            }

            $primaryKeys = [];
            foreach ($primaryKeyRows as $row) {
                $row = (array) $row;
                $primaryKeys[Helpers::columnKey(
                    (string) $row['table_name'],
                    (string) $row['column_name'],
                )] = true;
            }

            $foreignKeys = [];
            foreach ($foreignKeyRows as $row) {
                $row = (array) $row;
                $foreignKeys[Helpers::columnKey(
                    (string) $row['table_name'],
                    (string) $row['column_name'],
                )] = [
                    'table' => $row['ref_table'],
                    'column' => $row['ref_column'],
                ];
            }

            $tables = [];
            foreach ($tableRows as $table) {
                $name = (string) ((array) $table)['table_name'];

                $columns = [];
                foreach ($columnsByTable[$name] ?? [] as $row) {
                    $columns[] = $this->toRawColumn($row, $primaryKeys, $foreignKeys);
                }

                $tables[] = [
                    'name' => $name,
                    'columns' => $columns,
                    'rowsApprox' => $rowCounts[$name] ?? null,
                ];
            }

            return ['engine' => 'hana', 'tables' => $tables];
        } catch (Throwable $e) {
            throw $this->wrapIntrospection($e);
        }
    }

    /**
     * @return array<int|string, mixed>
     */
    private function fetch(PDO $handle, string $sql): array
    {
        return $handle->query($sql)->fetchAll();
    }

    /**
     * Best-effort row estimates. Each source is tried in order until one works;
     * when none is available the estimate is reported as "unknown" (`null`)
     * rather than failing introspection.
     *
     * @return array<string, int>
     */
    private function loadRowCounts(PDO $handle): array
    {
        $counts = [];

        foreach (Queries::rowCountSources() as $source) {
            try {
                foreach ($this->fetch($handle, $source) as $row) {
                    $row = (array) $row;
                    $count = $this->toNumber($row['record_count'] ?? null);
                    $table = (string) ($row['table_name'] ?? '');

                    if ($table !== '' && $count !== null && $count >= 0) {
                        $counts[$table] = $count;
                    }
                }

                return $counts;
            } catch (Throwable) {
                // Optional metadata: try the next source, otherwise report null.
            }
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $primaryKeys
     * @param array<string, array{table: mixed, column: mixed}> $foreignKeys
     * @return array<string, mixed>
     */
    private function toRawColumn(mixed $row, array $primaryKeys, array $foreignKeys): array
    {
        $row = (array) $row;
        $key = Helpers::columnKey((string) $row['table_name'], (string) $row['column_name']);

        return [
            'name' => $row['column_name'],
            'dataType' => $row['data_type'],
            'nullable' => $row['is_nullable'] === 'TRUE',
            'primaryKey' => isset($primaryKeys[$key]),
            'defaultValue' => $row['column_default'],
            'foreignKey' => $foreignKeys[$key] ?? null,
            'ordinal' => (int) $row['ordinal'],
        ];
    }

    private function toNumber(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
