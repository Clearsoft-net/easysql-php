<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Db2;

use Clearsoft\EasySQL\Connectors\BaseConnector;
use Clearsoft\EasySQL\Connectors\Helpers;
use PDO;
use Throwable;

/**
 * IBM Db2 connector — local schema introspection and SELECT execution.
 *
 * Metadata is read from the `SYSCAT` catalog in a fixed number of batched
 * queries — tables, columns, primary keys and foreign keys are each fetched once
 * for the whole schema and assembled in memory, so a schema with N tables costs
 * four round trips rather than 4N. The customer database is introspected locally
 * and only the schema reaches the API, which never stores credentials.
 *
 * No global state: several connections can be open at the same time.
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
        return 'Db2';
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
     * package: ['engine' => 'db2', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $handle = $this->requireHandle();
        assert($handle instanceof PDO);

        try {
            // Four batched queries: one per metadata concern, never one per table.
            $tableRows = $this->fetchAll($handle, Queries::TABLES);
            $columnRows = $this->fetchAll($handle, Queries::COLUMNS);
            $primaryKeyRows = $this->fetchAll($handle, Queries::PRIMARY_KEYS);
            $foreignKeyRows = $this->fetchAll($handle, Queries::FOREIGN_KEYS);

            $columnsByTable = [];
            foreach ($columnRows as $row) {
                $columnsByTable[$row['table_name']][] = $row;
            }

            $primaryKeys = [];
            foreach ($primaryKeyRows as $row) {
                $primaryKeys[Helpers::columnKey($row['table_name'], $row['column_name'])] = true;
            }

            $foreignKeys = [];
            foreach ($foreignKeyRows as $row) {
                $foreignKeys[Helpers::columnKey($row['table_name'], $row['column_name'])] = [
                    'table' => $row['ref_table'],
                    'column' => $row['ref_column'],
                ];
            }

            $tables = [];
            foreach ($tableRows as $table) {
                $name = $table['table_name'];

                $columns = [];
                foreach ($columnsByTable[$name] ?? [] as $row) {
                    $key = Helpers::columnKey($name, $row['column_name']);
                    $columns[] = [
                        'name' => $row['column_name'],
                        'dataType' => $row['data_type'],
                        'nullable' => $row['nulls'] === 'Y',
                        'primaryKey' => isset($primaryKeys[$key]),
                        'defaultValue' => $row['column_default'],
                        'foreignKey' => $foreignKeys[$key] ?? null,
                        'ordinal' => (int) $row['ordinal'],
                    ];
                }

                $tables[] = [
                    'name' => $name,
                    'columns' => $columns,
                    'rowsApprox' => $this->normalizeCard($table['rows_approx']),
                ];
            }

            return ['engine' => 'db2', 'tables' => $tables];
        } catch (Throwable $e) {
            throw $this->wrapIntrospection($e);
        }
    }

    /**
     * `SYSCAT.TABLES.CARD` is `-1` when statistics have not been collected; the
     * connector reports that as "unknown" rather than a bogus row count.
     */
    private function normalizeCard(mixed $value): ?int
    {
        if ($value === null || !is_numeric($value)) {
            return null;
        }

        $card = (int) $value;

        return $card >= 0 ? $card : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAll(PDO $handle, string $sql): array
    {
        $stmt = $handle->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
