<?php

declare(strict_types=1);

/**
 * Sample 13 — End-to-end local execution loop (the core EasySQL flow).
 *
 *   createQuery  ->  execute the generated SQL locally  ->  answerQuery  ->  read the final answer
 *
 * The API generates SQL/answer/chart; the customer database is only touched by
 * this process, so credentials never leave the machine.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=... CONNECTOR_ID=conn_... \
 *   MYSQL_HOST=127.0.0.1 MYSQL_USER=readonly MYSQL_PASSWORD=secret MYSQL_DATABASE=shop \
 *   php samples/13-local-execution-flow.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;
use Clearsoft\EasySQL\Client\Models\QueryResponse;
use Clearsoft\EasySQL\Connectors\MySQL\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\MySQL\Connector as MySQLConnector;

$client = new Client([
    'base_url' => getenv('EASYSQL_BASE_URL') ?: 'https://api.easysql.net',
    'access_token' => getenv('EASYSQL_ACCESS_TOKEN') ?: '',
]);
$connectorId = getenv('CONNECTOR_ID') ?: 'conn_abc123';

// 1. Ask a question — the API generates the SQL and returns immediately.
$query = QueryResponse::fromArray($client->createQuery([
    'connector_id' => $connectorId,
    'question' => 'How many users signed up last month?',
]));
echo "Query {$query->id} (status: {$query->status})" . PHP_EOL;

if (!$query->needs_local_execution || $query->sql_generated === null) {
    // The API can answer without local execution on its own.
    echo "Answer: {$query->answer}" . PHP_EOL;
    exit(0);
}

// 2. Execute the generated SQL against the local database (read-only).
$connector = new MySQLConnector(new ConnectionConfig(
    host: getenv('MYSQL_HOST') ?: '127.0.0.1',
    port: (int) (getenv('MYSQL_PORT') ?: 3306),
    user: getenv('MYSQL_USER') ?: 'root',
    password: getenv('MYSQL_PASSWORD') ?: '',
    database: getenv('MYSQL_DATABASE') ?: 'shop',
));
$connector->connect();

try {
    echo "Generated SQL: {$query->sql_generated}" . PHP_EOL;
    $result = $connector->execute($query->sql_generated);
    echo "Local result: {$result['row_count']} rows in {$result['duration_ms']}ms" . PHP_EOL;
} finally {
    $connector->close();
}

// 3. Post the rows back so the API can produce the answer and chart.
$client->answerQuery([
    'columns' => $result['columns'],
    'rows' => $result['rows'],
], $query->id);

// 4. Read the completed query.
$final = QueryResponse::fromArray($client->getQuery($query->id));
echo "Status: {$final->status}" . PHP_EOL;
echo "Answer: " . ($final->answer ?? '(pending)') . PHP_EOL;
