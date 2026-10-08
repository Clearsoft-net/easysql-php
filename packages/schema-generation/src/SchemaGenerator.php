<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\SchemaGeneration;

/**
 * Transforms raw connector introspection output into the schema payload
 * the EasySQL API consumes on POST /v1/connections and POST /v1/connections/{id}/sync.
 *
 * Responsibilities:
 *  - map engine-native types into the canonical schema vocabulary
 *  - guarantee deterministic output (stable ordering of tables/columns)
 *  - validate the input shape produced by the connector packages
 *
 * This package performs NO network, filesystem or database access.
 *
 * Input shape (produced by every connector package):
 *   [
 *     'engine' => 'mysql',
 *     'tables' => [
 *       [
 *         'name' => 'users',
 *         'columns' => [
 *           [
 *             'name' => 'id',
 *             'dataType' => 'int',         // engine-native, verbatim from the driver
 *             'nullable' => false,
 *             'primaryKey' => true,
 *             'defaultValue' => null,      // string|null
 *             'foreignKey' => null,        // ['table' => ..., 'column' => ...]|null
 *             'ordinal' => 1,              // int|null
 *           ],
 *         ],
 *         'rowsApprox' => 42,              // int|null
 *       ],
 *     ],
 *   ]
 *
 * Output shape (the API payload):
 *   [
 *     [
 *       'name' => 'users',
 *       'columns' => [ 'name', 'type', 'nullable', 'primary_key', 'default', 'foreign_key' ],
 *       'rows_approx' => 42,
 *     ],
 *   ]
 *
 * Determinism contract (mirrored by the TypeScript sibling package):
 *  - tables are sorted by name using a byte-wise comparison (PHP strcmp /
 *    ASCII order) — the TS package must NOT use localeCompare();
 *  - columns keep the declared order when every column reports an ordinal and
 *    fall back to name order otherwise;
 *  - ties on the sort keys are stable (PHP sort is stable since 8.0), so the
 *    same input always produces the same output.
 */
final class SchemaGenerator
{
    /**
     * @param array $rawSchema Raw introspection output from a connector package.
     * @return list<array<string, mixed>> Schema payload accepted by the API.
     */
    public function generate(array $rawSchema): array
    {
        RawSchemaValidator::validate($rawSchema);

        $engine = $rawSchema['engine'];
        $payload = [];

        $tables = $rawSchema['tables'];
        usort($tables, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        foreach ($tables as $table) {
            $columns = $this->sortColumns($table['columns']);

            $mapped = [];
            foreach ($columns as $column) {
                $mapped[] = [
                    'name' => $column['name'],
                    'type' => TypeMapper::map($engine, $column['dataType']),
                    'nullable' => (bool) $column['nullable'],
                    'primary_key' => (bool) ($column['primaryKey'] ?? false),
                    'default' => $column['defaultValue'] ?? null,
                    'foreign_key' => $this->mapForeignKey($column['foreignKey'] ?? null),
                ];
            }

            $payload[] = [
                'name' => $table['name'],
                'columns' => $mapped,
                'rows_approx' => $table['rowsApprox'] ?? null,
            ];
        }

        return $payload;
    }

    /**
     * Deterministic JSON encoding of a generated payload — the same database
     * must produce byte-for-byte identical output across runs and languages.
     */
    public function toJson(array $payload): string
    {
        return (string) json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Declared order when the connector provides an ordinal; name order
     * otherwise. Both keys are compared independently so a partially-ordered
     * input stays stable.
     *
     * @param list<array<string, mixed>> $columns
     * @return list<array<string, mixed>>
     */
    private function sortColumns(array $columns): array
    {
        usort($columns, static function (array $a, array $b): int {
            if (isset($a['ordinal']) && isset($b['ordinal'])) {
                return ((int) $a['ordinal']) <=> ((int) $b['ordinal']);
            }

            return strcmp($a['name'], $b['name']);
        });

        return $columns;
    }

    /**
     * @return array{table: string, column: string}|null
     */
    private function mapForeignKey(mixed $foreignKey): ?array
    {
        if (!is_array($foreignKey)) {
            return null;
        }

        $table = $foreignKey['table'] ?? null;
        $column = $foreignKey['column'] ?? null;
        if (!is_string($table) || !is_string($column) || $table === '' || $column === '') {
            return null;
        }

        return ['table' => $table, 'column' => $column];
    }
}
