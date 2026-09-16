<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\SchemaGeneration;

use InvalidArgumentException;

/**
 * @internal Validates the raw schema input shape produced by the connectors.
 */
final class RawSchemaValidator
{
    /**
     * @param array $rawSchema
     * @throws InvalidArgumentException When the input shape is malformed.
     */
    public static function validate(array $rawSchema): void
    {
        if (!array_key_exists('tables', $rawSchema) || !is_array($rawSchema['tables'])) {
            throw new InvalidArgumentException(
                "Invalid raw schema: expected key 'tables' containing a list of tables.",
            );
        }

        foreach ($rawSchema['tables'] as $index => $table) {
            if (!is_array($table)) {
                throw new InvalidArgumentException(
                    "Invalid raw schema: table at index {$index} is not an array.",
                );
            }
            self::assertString($table, 'name', "table[{$index}]");

            if (!array_key_exists('columns', $table) || !is_array($table['columns'])) {
                throw new InvalidArgumentException(
                    "Invalid raw schema: table[{$index}] ('{$table['name']}') is missing the 'columns' list.",
                );
            }

            foreach ($table['columns'] as $columnIndex => $column) {
                if (!is_array($column)) {
                    throw new InvalidArgumentException(
                        "Invalid raw schema: column at table[{$index}].columns[{$columnIndex}] is not an array.",
                    );
                }
                self::assertString($column, 'name', "table[{$index}].columns[{$columnIndex}]");
                self::assertString($column, 'type', "table[{$index}].columns[{$columnIndex}]");
            }
        }
    }

    private static function assertString(array $value, string $key, string $where): void
    {
        if (!array_key_exists($key, $value) || !is_string($value[$key])) {
            throw new InvalidArgumentException(
                "Invalid raw schema: {$where} is missing a valid string '{$key}'.",
            );
        }
    }
}
