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
    'tables' => [
        [
            'name' => 'orders',
            'columns' => [
                ['name' => 'id', 'type' => 'INT', 'nullable' => false, 'primary_key' => true, 'default' => null, 'foreign_key' => null],
                ['name' => 'customer_id', 'type' => 'INT', 'nullable' => false, 'primary_key' => false, 'default' => null, 'foreign_key' => ['table' => 'customers', 'column' => 'id']],
            ],
            'rows_approx' => 1523,
        ],
        [
            'name' => 'customers',
            'columns' => [
                ['name' => 'id', 'type' => 'INT', 'nullable' => false, 'primary_key' => true, 'default' => null, 'foreign_key' => null],
                ['name' => 'email', 'type' => 'VARCHAR', 'nullable' => false, 'primary_key' => false, 'default' => null, 'foreign_key' => null],
                ['name' => 'metadata', 'type' => '', 'nullable' => true, 'primary_key' => false, 'default' => null, 'foreign_key' => null],
            ],
            'rows_approx' => 210,
        ],
    ],
];

$generator = new SchemaGenerator();
$schema = $generator->generate($raw);

// Tables are sorted by name, types lowercased (empty SQLite type -> "blob").
foreach ($schema as $table) {
    echo "{$table['name']} (" . count($table['columns']) . " columns)" . PHP_EOL;
}

// Deterministic: the same input always produces the same JSON.
$first = $generator->toJson($schema);
$second = $generator->toJson($generator->generate($raw));
echo ($first === $second ? "Deterministic OK" : "NON-DETERMINISTIC") . PHP_EOL;
echo "sha256: " . hash('sha256', $first) . PHP_EOL;
