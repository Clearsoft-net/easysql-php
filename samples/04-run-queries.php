<?php

declare(strict_types=1);

/**
 * Sample 04 — Natural-language queries: create, read and list.
 *
 * The API generates and validates SQL from a cached connection schema. The
 * client executes it locally; customer rows never get posted to the API.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=easysql_sk_... CONNECTION_ID=conn_... php samples/04-run-queries.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;

$client = new Client([
    'base_url' => getenv('EASYSQL_BASE_URL') ?: 'https://api.easysql.net',
    'access_token' => getenv('EASYSQL_ACCESS_TOKEN') ?: '',
]);
$connectionId = getenv('CONNECTION_ID') ?: 'conn_abc123';

// Create a query — returns generated SQL and indicates that local execution is needed.
$query = $client->createQuery([
    'connection_id' => $connectionId,
    'question' => 'How many users signed up last month?',
]);
$queryId = $query['id'];
echo "Created query {$queryId} (status: {$query['status']})" . PHP_EOL;
echo "Generated SQL: " . ($query['sql_generated'] ?? '(not generated)') . PHP_EOL;

// Fetch a single query.
$query = $client->getQuery($queryId);
echo "Question: {$query['question']}" . PHP_EOL;

// List history with cursor pagination.
$page = $client->listQueries(['limit' => 10]);
echo "Showing " . count($page['items'] ?? []) . " queries." . PHP_EOL;
if (!empty($page['next_cursor'])) {
    echo "Next cursor: {$page['next_cursor']}" . PHP_EOL;
}
