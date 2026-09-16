<?php

declare(strict_types=1);

/**
 * Sample 06 — MySQL: introspect locally, generate the schema payload and sync it.
 *
 * Credentials stay on the machine: only the schema payload is sent to EasySQL.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=... CONNECTOR_ID=conn_... \
 *   MYSQL_HOST=127.0.0.1 MYSQL_USER=readonly MYSQL_PASSWORD=secret MYSQL_DATABASE=shop \
 *   php samples/06-conn-mysql-sync.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;
use Clearsoft\EasySQL\Connectors\MySQL\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\MySQL\Connector as MySQLConnector;
use Clearsoft\EasySQL\SchemaGeneration\SchemaGenerator;

$connector = new MySQLConnector(new ConnectionConfig(
    host: getenv('MYSQL_HOST') ?: '127.0.0.1',
    port: (int) (getenv('MYSQL_PORT') ?: 3306),
    user: getenv('MYSQL_USER') ?: 'root',
    password: getenv('MYSQL_PASSWORD') ?: '',
    database: getenv('MYSQL_DATABASE') ?: 'shop',
));
$connector->connect();

try {
    // 1. Introspect the real database (raw driver metadata).
    $raw = $connector->introspect();
    echo "Introspected " . count($raw['tables']) . " tables." . PHP_EOL;

    // 2. Normalize into the API schema payload (deterministic, no I/O).
    $schema = (new SchemaGenerator())->generate($raw);

    // 3. Push the schema to EasySQL (no credentials involved).
    $client = new Client([
        'base_url' => getenv('EASYSQL_BASE_URL') ?: 'https://api.easysql.net',
        'access_token' => getenv('EASYSQL_ACCESS_TOKEN') ?: '',
    ]);
    $client->syncConnector(['schema' => $schema], getenv('CONNECTOR_ID') ?: 'conn_abc123');
    echo "Schema synced." . PHP_EOL;

    // 4. The API returns generated SQL — execute it locally.
    $result = $connector->execute('SELECT * FROM users LIMIT 10');
    echo "Fetched {$result['row_count']} rows in {$result['duration_ms']}ms." . PHP_EOL;
} finally {
    $connector->close();
}
