<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Oracle;

use Clearsoft\EasySQL\Connectors\BaseConnector;
use Clearsoft\EasySQL\Connectors\Helpers;
use PDO;
use Throwable;

/**
 * Oracle Database connector — local schema introspection and SELECT execution.
 *
 * The customer database is introspected locally and only the schema reaches the
 * API, which never stores credentials or executes SQL.
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
        return 'Oracle';
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
     * package: ['engine' => 'oracle', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $handle = $this->requireHandle();
        assert($handle instanceof PDO);

        try {
            $tableRows = $this->fetchAll($handle, Queries::TABLES);
            $columnRows = $this->fetchAll($handle, Queries::COLUMNS);
            $primaryKeyRows = $this->fetchAll($handle, Queries::PRIMARY_KEYS);
            $foreignKeyRows = $this->fetchAll($handle, Queries::FOREIGN_KEYS);

            $tables = [];
            foreach ($tableRows as $row) {
                $tables[$row['table_name']] = [
                    'name' => $row['table_name'],
                    'columns' => [],
                    'rowsApprox' => $row['rows_approx'] !== null
                        ? (int) $row['rows_approx']
                        : null,
                ];
            }

            $columns = [];
            foreach ($columnRows as $row) {
                $tableName = $row['table_name'];
                if (!isset($tables[$tableName])) {
                    continue;
                }

                $index = count($tables[$tableName]['columns']);
                $tables[$tableName]['columns'][] = [
                    'name' => $row['column_name'],
                    'dataType' => $row['data_type'],
                    'nullable' => $row['nullable'] === 'Y',
                    'primaryKey' => false,
                    'defaultValue' => $this->normalizeDefault($row['data_default']),
                    'foreignKey' => null,
                    'ordinal' => (int) $row['column_id'],
                ];
                $columns[Helpers::columnKey($tableName, $row['column_name'])] = [$tableName, $index];
            }

            foreach ($primaryKeyRows as $row) {
                $key = Helpers::columnKey($row['table_name'], $row['column_name']);
                if (isset($columns[$key])) {
                    [$tableName, $index] = $columns[$key];
                    $tables[$tableName]['columns'][$index]['primaryKey'] = true;
                }
            }

            foreach ($foreignKeyRows as $row) {
                $key = Helpers::columnKey($row['table_name'], $row['column_name']);
                if (isset($columns[$key])) {
                    [$tableName, $index] = $columns[$key];
                    $tables[$tableName]['columns'][$index]['foreignKey'] = [
                        'table' => $row['foreign_table'],
                        'column' => $row['foreign_column'],
                    ];
                }
            }

            return ['engine' => 'oracle', 'tables' => array_values($tables)];
        } catch (Throwable $e) {
            throw $this->wrapIntrospection($e);
        }
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

    /**
     * Oracle returns `DATA_DEFAULT` as a padded `LONG`; normalise it to trimmed
     * text, treating whitespace-only values as `null`.
     */
    private function normalizeDefault(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
