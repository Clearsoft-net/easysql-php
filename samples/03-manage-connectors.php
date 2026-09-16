<?php

declare(strict_types=1);

/**
 * Sample 03 — Manage database connectors (schema-only, no credentials sent).
 *
 * Connectors are created with a schema payload only; the EasySQL API never
 * stores credentials and never executes SQL. Path parameters are explicit.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=easysql_sk_... php samples/03-manage-connectors.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;

$client = new Client([
    'base_url' => getenv('EASYSQL_BASE_URL') ?: 'https://api.easysql.net',
    'access_token' => getenv('EASYSQL_ACCESS_TOKEN') ?: '',
]);

// Create a schema-only connector (schema omitted here — see samples 06-08 for
// a full introspection + schema-generation flow).
$created = $client->createConnector([
    'name' => 'Sample MySQL',
    'type' => 'mysql',
]);
$connectorId = $created['id'];
echo "Created connector {$connectorId}" . PHP_EOL;

// Path parameters are explicit: getConnector(string $connector_id).
$connector = $client->getConnector($connectorId);
echo "Fetched: {$connector['name']}" . PHP_EOL;

// Push a refreshed schema.
$client->syncConnector([
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
], $connectorId);
echo "Schema synced." . PHP_EOL;

// Read back what the API stored.
$schema = $client->getConnectorSchema($connectorId);
echo "Stored tables: " . count($schema['tables'] ?? $schema) . PHP_EOL;

// Rename and delete.
$client->updateConnector(['name' => 'Sample MySQL (renamed)'], $connectorId);
$client->deleteConnector($connectorId);
echo "Updated and deleted {$connectorId}" . PHP_EOL;
