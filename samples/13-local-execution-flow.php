<?php

declare(strict_types=1);

/**
 * Sample 13 — End-to-end local execution loop (the core EasySQL flow).
 *
 *   createQuery  ->  execute the generated SQL locally  ->  render the answer
 *
 * The API generates the SQL from the connection's cached schema; the customer
 * database is only touched by this process, so credentials never leave the machine.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=... CONNECTION_ID=conn_... \
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
$connectionId = getenv('CONNECTION_ID') ?: 'conn_abc123';

// 1. Ask a question — the API generates + validates the SQL and returns immediately.
$query = QueryResponse::fromArray($client->createQuery([
    'connection_id' => $connectionId,
    'question' => 'How many users signed up last month?',
]));
echo "Query {$query->id} (status: {$query->status})" . PHP_EOL;

if (!$query->needs_local_execution || $query->sql_generated === null) {
    // The API answered on its own — nothing to execute locally.
    echo "No local execution needed." . PHP_EOL;
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

// 3. The rows never leave the machine — the client-side runtime renders the
// answer/chart locally (the API is schema-only: it only generates SQL).
echo "Answer rows: " . json_encode(array_slice($result['rows'], 0, 5)) . PHP_EOL;
