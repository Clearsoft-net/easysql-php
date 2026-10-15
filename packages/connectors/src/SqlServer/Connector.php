<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\SqlServer;

use Clearsoft\EasySQL\Connectors\BaseConnector;
use PDO;
use Throwable;

/**
 * Microsoft SQL Server connector — local schema introspection and SELECT
 * execution.
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
        return 'SQL Server';
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
     * Introspect the configured database.
     *
     * Returns the raw introspection shape consumed by the schema-generation
     * package: ['engine' => 'sqlserver', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $handle = $this->requireHandle();
        assert($handle instanceof PDO);

        try {
            $tablesRows = $this->fetchAll($handle, Queries::TABLES);
            $columnRows = $this->fetchAll($handle, Queries::COLUMNS);

            $columnsByTable = [];
            foreach ($columnRows as $row) {
                $columnsByTable[$row['table_id']][] = [
                    'name' => $row['column_name'],
                    'dataType' => $row['data_type'],
                    'nullable' => $this->toBool($row['is_nullable']),
                    'primaryKey' => $this->toBool($row['is_primary_key']),
                    'defaultValue' => $row['default_value'],
                    'foreignKey' => $row['foreign_table'] !== null
                        && $row['foreign_column'] !== null
                        ? [
                            'table' => $row['foreign_table'],
                            'column' => $row['foreign_column'],
                        ]
                        : null,
                    'ordinal' => (int) $row['ordinal'],
                ];
            }

            $tables = [];
            foreach ($tablesRows as $row) {
                $tables[] = [
                    'name' => $row['table_name'],
                    'columns' => $columnsByTable[$row['table_id']] ?? [],
                    'rowsApprox' => $row['rows_approx'] !== null
                        ? (int) $row['rows_approx']
                        : null,
                ];
            }

            return ['engine' => 'sqlserver', 'tables' => $tables];
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
     * PDO sqlsrv returns BITs as PHP bool, int or '0'/'1' strings depending on
     * the build; normalize them all into a real bool.
     */
    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, ['t', 'true', '1', 1, 'y', 'yes'], true);
    }
}
