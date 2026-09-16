<?php

declare(strict_types=1);

/**
 * Sample 04 — Natural-language queries: create, poll, list and answer.
 *
 * The API generates SQL; the client executes it locally (samples 06-08) and
 * posts the result back with answerQuery(). Here we only exercise the API
 * surface.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=easysql_sk_... CONNECTOR_ID=conn_... php samples/04-run-queries.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;

$client = new Client([
    'base_url' => getenv('EASYSQL_BASE_URL') ?: 'https://api.easysql.net',
    'access_token' => getenv('EASYSQL_ACCESS_TOKEN') ?: '',
]);
$connectorId = getenv('CONNECTOR_ID') ?: 'conn_abc123';

// Create a query — returns immediately with status "processing".
$query = $client->createQuery([
    'connector_id' => $connectorId,
    'question' => 'How many users signed up last month?',
]);
$queryId = $query['id'];
echo "Created query {$queryId} (status: {$query['status']})" . PHP_EOL;

// Fetch a single query.
$query = $client->getQuery($queryId);
echo "Generated SQL: " . ($query['sql_generated'] ?? '(pending)') . PHP_EOL;

// List with pagination (query strategy takes an array).
$page = $client->listQueries(['page' => 1, 'per_page' => 10]);
echo "Showing {$page['total']} queries." . PHP_EOL;

// Send a locally-executed result set back to the API (schema-only connectors).
$client->answerQuery([
    'columns' => ['count'],
    'rows' => [['count' => 42]],
], $queryId);
echo "Answer posted." . PHP_EOL;
