<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Snowflake;

use Clearsoft\EasySQL\Connectors\BaseConnector;
use PDO;
use Throwable;

/**
 * Snowflake connector — local schema introspection and SELECT execution.
 *
 * Snowflake is reached through ODBC (`pdo_odbc`). The customer database is
 * introspected locally and only the schema reaches the API, which never stores
 * credentials or executes SQL.
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
        return 'Snowflake';
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
     * Introspect the configured database/schema.
     *
     * Returns the raw introspection shape consumed by the schema-generation
     * package: ['engine' => 'snowflake', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     * Snowflake exposes no cheap row count through `INFORMATION_SCHEMA`, so
     * `rowsApprox` is always `null`.
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $handle = $this->requireHandle();
        assert($handle instanceof PDO);

        try {
            $tableRows = $handle->query(Queries::TABLES)->fetchAll();

            $columnsByTable = [];
            foreach ($handle->query(Queries::COLUMNS)->fetchAll() as $row) {
                $row = (array) $row;
                $columnsByTable[(string) $row['table_name']][] = $this->toRawColumn($row);
            }

            $names = array_map(
                static fn (mixed $row): string => (string) ((array) $row)['table_name'],
                $tableRows,
            );
            sort($names, SORT_STRING);

            $tables = [];
            foreach ($names as $name) {
                $columns = $columnsByTable[$name] ?? [];
                usort(
                    $columns,
                    static fn (array $a, array $b): int => (int) ($a['ordinal'] ?? 0) <=> (int) ($b['ordinal'] ?? 0),
                );

                $tables[] = [
                    'name' => $name,
                    'columns' => $columns,
                    'rowsApprox' => null,
                ];
            }

            return ['engine' => 'snowflake', 'tables' => $tables];
        } catch (Throwable $e) {
            throw $this->wrapIntrospection($e);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toRawColumn(array $row): array
    {
        return [
            'name' => $row['column_name'],
            'dataType' => $row['data_type'],
            'nullable' => $this->toBool($row['is_nullable']),
            'primaryKey' => $this->toBool($row['primary_key']),
            'defaultValue' => $row['column_default'],
            'foreignKey' => $row['foreign_table'] !== null && $row['foreign_column'] !== null
                ? [
                    'table' => $row['foreign_table'],
                    'column' => $row['foreign_column'],
                ]
                : null,
            'ordinal' => (int) $row['ordinal_position'],
        ];
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        return in_array($value, ['t', 'true', '1', 'y', 'yes', 'YES'], true);
    }
}
