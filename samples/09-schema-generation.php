<?php

declare(strict_types=1);

/**
 * Sample 09 — Schema generation in isolation (no database, no network).
 *
 * The package is pure: feed it the raw shape produced by any connector and it
 * returns the deterministic API payload. Handy for tests and for caching.
 *
 * Run:
 *   php samples/09-schema-generation.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\SchemaGeneration\SchemaGenerator;

$raw = [
    'engine' => 'mysql',
    'tables' => [
        [
            'name' => 'orders',
            'columns' => [
                ['name' => 'id', 'dataType' => 'int', 'nullable' => false, 'primaryKey' => true, 'defaultValue' => null, 'foreignKey' => null, 'ordinal' => 1],
                ['name' => 'customer_id', 'dataType' => 'int', 'nullable' => false, 'primaryKey' => false, 'defaultValue' => null, 'foreignKey' => ['table' => 'customers', 'column' => 'id'], 'ordinal' => 2],
            ],
            'rowsApprox' => 1523,
        ],
        [
            'name' => 'customers',
            'columns' => [
                ['name' => 'id', 'dataType' => 'int', 'nullable' => false, 'primaryKey' => true, 'defaultValue' => null, 'foreignKey' => null, 'ordinal' => 1],
                ['name' => 'email', 'dataType' => 'varchar(255)', 'nullable' => false, 'primaryKey' => false, 'defaultValue' => null, 'foreignKey' => null, 'ordinal' => 2],
                ['name' => 'metadata', 'dataType' => '', 'nullable' => true, 'primaryKey' => false, 'defaultValue' => null, 'foreignKey' => null, 'ordinal' => 3],
            ],
            'rowsApprox' => 210,
        ],
    ],
];

$generator = new SchemaGenerator();
$schema = $generator->generate($raw);

// Tables are sorted by name, types mapped to the canonical vocabulary
// (empty SQLite type -> "blob", "varchar" -> "string", "int" -> "integer").
foreach ($schema as $table) {
    echo "{$table['name']} (" . count($table['columns']) . " columns)" . PHP_EOL;
}

// Deterministic: the same input always produces the same JSON.
$first = $generator->toJson($schema);
$second = $generator->toJson($generator->generate($raw));
echo ($first === $second ? "Deterministic OK" : "NON-DETERMINISTIC") . PHP_EOL;
echo "sha256: " . hash('sha256', $first) . PHP_EOL;
