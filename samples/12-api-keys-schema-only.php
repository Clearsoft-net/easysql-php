<?php

declare(strict_types=1);

/**
 * Sample 12 — API keys and schema-only connectors (WordPress-plugin style).
 *
 * API keys are the credential for schema-only clients: the database is
 * queried locally and only the schema (and later the result set) reaches the API.
 * The full key is shown only once, on creation.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=... php samples/12-api-keys-schema-only.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;

$client = new Client([
    'base_url' => getenv('EASYSQL_BASE_URL') ?: 'https://api.easysql.net',
    'access_token' => getenv('EASYSQL_ACCESS_TOKEN') ?: '',
]);

// 1. Create an API key for the external client.
$created = $client->createApiKey(['name' => 'WordPress plugin']);
$apiKey = $created['key']; // easysql_sk_... — shown once
echo "Store this key securely: {$apiKey}" . PHP_EOL;

// 2. Create a schema-only connector (no credentials, no config).
$connector = $client->createConnector([
    'name' => 'WP Database',
    'type' => 'mysql',
    'schema' => [[
        'name' => 'posts',
        'columns' => [
            ['name' => 'id', 'type' => 'bigint', 'nullable' => false, 'primary_key' => true, 'default' => null, 'foreign_key' => null],
            ['name' => 'title', 'type' => 'text', 'nullable' => false, 'primary_key' => false, 'default' => null, 'foreign_key' => null],
        ],
        'rows_approx' => 1250,
    ]],
]);
$connectorId = $connector['id'];
echo "Schema-only connector created: {$connectorId}" . PHP_EOL;

// 3. The external client authenticates with the API key and runs a query.
$external = new Client([
    'base_url' => getenv('EASYSQL_BASE_URL') ?: 'https://api.easysql.net',
    'access_token' => $apiKey,
]);
$query = $external->createQuery([
    'connector_id' => $connectorId,
    'question' => 'How many posts were published this month?',
]);
echo "Query {$query['id']} — needs local execution: " . json_encode((bool) ($query['needs_local_execution'] ?? true)) . PHP_EOL;

// The external client executes the generated SQL against its local DB and
// posts the rows back with answerQuery() (see sample 13 for the full loop).

// 4. Cleanup: revoke the key and delete the connector.
$external->deleteApiKey($created['id']);
$client->deleteConnector($connectorId);
echo "Revoked key and deleted connector." . PHP_EOL;
