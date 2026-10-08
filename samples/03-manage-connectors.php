<?php

declare(strict_types=1);

/**
 * Sample 03 — Manage database connections (schema-only, no credentials sent).
 *
 * Connections are created with a schema payload only; the EasySQL API never
 * stores credentials and never executes SQL. Path parameters are explicit.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=easysql_sk_... php samples/03-manage-connections.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;

$client = new Client([
    'base_url' => getenv('EASYSQL_BASE_URL') ?: 'https://api.easysql.net',
    'access_token' => getenv('EASYSQL_ACCESS_TOKEN') ?: '',
]);

// Create a schema-only connection (schema omitted here — see samples 06-08 for
// a full introspection + schema-generation flow).
$created = $client->createConnection([
    'name' => 'Sample MySQL',
    'type' => 'mysql',
]);
$connectionId = $created['id'];
echo "Created connection {$connectionId}" . PHP_EOL;

// Path parameters are explicit: getConnection(string $connection_id).
$connection = $client->getConnection($connectionId);
echo "Fetched: {$connection['name']}" . PHP_EOL;

// Push a refreshed schema.
$client->syncConnection([
    'schema' => [[
        'name' => 'users',
        'columns' => [[
            'name' => 'id',
            'type' => 'integer',
            'nullable' => false,
            'primary_key' => true,
            'default' => null,
            'foreign_key' => null,
        ]],
        'rows_approx' => 0,
    ]],
], $connectionId);
echo "Schema synced." . PHP_EOL;

// Read back what the API stored.
$schema = $client->getConnectionSchema($connectionId);
echo "Stored tables: " . count($schema['tables'] ?? $schema) . PHP_EOL;

// Rename and delete.
$client->updateConnection(['name' => 'Sample MySQL (renamed)'], $connectionId);
$client->deleteConnection($connectionId);
echo "Updated and deleted {$connectionId}" . PHP_EOL;
