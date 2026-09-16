<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\SchemaGeneration;

use InvalidArgumentException;

/**
 * Transforms raw connector introspection output into the schema payload
 * the EasySQL API consumes on POST /v1/connectors and POST /v1/connectors/{id}/sync.
 *
 * Responsibilities:
 *  - map engine-native types into the schema vocabulary
 *  - guarantee deterministic output (stable ordering of tables/columns)
 *  - validate the input shape produced by the connector packages
 *
 * This package performs NO network, filesystem or database access.
 *
 * Input shape (produced by every connector package):
 *   [
 *     'tables' => [
 *       [
 *         'name' => 'users',
 *         'columns' => [
 *           [
 *             'name' => 'id',
 *             'type' => 'int',             // engine-native, verbatim from the driver
 *             'nullable' => false,
 *             'primary_key' => true,
 *             'default' => null,           // string|null
 *             'foreign_key' => null        // ['table' => ..., 'column' => ...]|null
 *           ],
 *         ],
 *         'rows_approx' => 42,             // int|null
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
 *  - columns keep the introspection order;
 *  - ties on the table name are stable (PHP sort is stable since 8.0), so the
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

        $payload = [];

        $tables = $rawSchema['tables'];
        usort($tables, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        foreach ($tables as $table) {
            $columns = [];
            foreach ($table['columns'] as $column) {
                $columns[] = [
                    'name' => $column['name'],
                    'type' => $this->mapType($column['type']),
                    'nullable' => (bool) $column['nullable'],
                    'primary_key' => (bool) $column['primary_key'],
                    'default' => $column['default'],
                    'foreign_key' => $this->mapForeignKey($column['foreign_key'] ?? null),
                ];
            }

            $payload[] = [
                'name' => $table['name'],
                'columns' => $columns,
                'rows_approx' => $table['rows_approx'] ?? null,
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
        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Map an engine-native type into the schema vocabulary.
     *
     * Types are normalized to lowercase (MySQL's information_schema already
     * reports lowercase types; SQLite reports uppercase pragma types) and an
     * empty declaration (SQLite allows untyped columns) falls back to 'blob'.
     */
    private function mapType(string $type): string
    {
        $normalized = strtolower(trim($type));

        return $normalized === '' ? 'blob' : $normalized;
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
