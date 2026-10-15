<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Firebird;

use Clearsoft\EasySQL\Connectors\BaseConnector;
use PDO;
use Throwable;

/**
 * Firebird connector — local schema introspection and SELECT execution.
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
        return 'Firebird';
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
     * Introspect the attached database.
     *
     * Returns the raw introspection shape consumed by the schema-generation
     * package: ['engine' => 'firebird', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $handle = $this->requireHandle();
        assert($handle instanceof PDO);

        try {
            $tableRows = $this->fetchAll($handle, Queries::TABLES);

            $tables = [];
            foreach ($tableRows as $row) {
                $name = $this->trimName($row['table_name']);

                $columns = $this->fetchAll($handle, Queries::COLUMNS, ['table' => $name]);
                $foreignKeys = $this->fetchAll(
                    $handle,
                    Queries::FOREIGN_KEYS,
                    ['table' => $name],
                );

                $fkByColumn = [];
                foreach ($foreignKeys as $fk) {
                    $fkByColumn[$this->trimName($fk['fk_column'])] = [
                        'table' => $this->trimName($fk['ref_table']),
                        'column' => $this->trimName($fk['ref_column']),
                    ];
                }

                $rawColumns = [];
                foreach ($columns as $column) {
                    $rawColumns[] = $this->toRawColumn($column, $fkByColumn);
                }

                $tables[] = [
                    'name' => $name,
                    'columns' => $rawColumns,
                    'rowsApprox' => $this->toNullableInt($row['rows_approx']),
                ];
            }

            return ['engine' => 'firebird', 'tables' => $tables];
        } catch (Throwable $e) {
            throw $this->wrapIntrospection($e);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, array{table: string, column: string}> $fkByColumn
     * @return array<string, mixed>
     */
    private function toRawColumn(array $row, array $fkByColumn): array
    {
        $name = $this->trimName($row['field_name']);
        $primaryKey = (int) ($row['pk'] ?? 0) > 0;

        return [
            'name' => $name,
            'dataType' => $this->toDataType($row),
            // A primary key is never nullable, even though RDB$NULL_FLAG is left
            // unset for some legacy primary-key declarations.
            'nullable' => $primaryKey ? false : (int) ($row['null_flag'] ?? 0) === 0,
            'primaryKey' => $primaryKey,
            'defaultValue' => $this->sanitizeDefault($row['default_source']),
            'foreignKey' => $fkByColumn[$name] ?? null,
            'ordinal' => (int) ($row['field_position'] ?? 0) + 1,
        ];
    }

    /**
     * Rebuilds a readable native type from the catalog's numeric descriptor.
     *
     * @param array<string, mixed> $row
     */
    private function toDataType(array $row): string
    {
        $typeName = $this->sanitizeDefault($row['type_name']) ?? 'UNKNOWN';
        $length = $this->toNullableNumber($row['character_length'])
            ?? $this->toNullableNumber($row['field_length']);
        $scale = abs((int) ($row['field_scale'] ?? 0));

        if ($typeName === 'TEXT') {
            return sprintf('VARCHAR(%d)', (int) ($length ?? 0));
        }
        if ($typeName === 'CHAR') {
            return sprintf('CHAR(%d)', (int) ($length ?? 0));
        }
        if ($typeName === 'VARYING') {
            return sprintf('VARCHAR(%d)', (int) ($length ?? 0));
        }
        if ($typeName === 'BLOB') {
            return 'BLOB';
        }
        if ($scale > 0) {
            $precision = $this->toNullableNumber($row['field_precision']) ?? $length;

            return sprintf('%s(%d,%d)', $typeName, (int) ($precision ?? 0), $scale);
        }

        return $typeName;
    }

    /**
     * Firebird stores `DEFAULT` clauses as the full source text; strip the
     * leading `DEFAULT` keyword and trim the rest.
     */
    private function sanitizeDefault(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^DEFAULT\s+(.+)$/is', $trimmed, $match) === 1) {
            return trim($match[1]);
        }

        return $trimmed;
    }

    /**
     * Trims Firebird's fixed-width, space-padded catalog names.
     */
    private function trimName(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function toNullableNumber(mixed $value): ?float
    {
        if ($value === null || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function toNullableInt(mixed $value): ?int
    {
        $number = $this->toNullableNumber($value);

        return $number === null ? null : (int) $number;
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function fetchAll(PDO $handle, string $sql, array $params = []): array
    {
        $stmt = $handle->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
