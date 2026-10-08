<?php

declare(strict_types=1);

/**
 * Sample 15 — ClickHouse: introspect locally and sync the canonical schema.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=... CONNECTION_ID=conn_... \
 *   CLICKHOUSE_HOST=127.0.0.1 CLICKHOUSE_USER=default CLICKHOUSE_PASSWORD=secret \
 *   CLICKHOUSE_DATABASE=default php samples/15-conn-clickhouse-sync.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;
use Clearsoft\EasySQL\Connectors\ClickHouse\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\ClickHouse\Connector as ClickHouseConnector;
use Clearsoft\EasySQL\SchemaGeneration\SchemaGenerator;

$connector = new ClickHouseConnector(new ConnectionConfig(
    host: getenv('CLICKHOUSE_HOST') ?: '127.0.0.1',
    port: getenv('CLICKHOUSE_PORT') !== false ? (int) getenv('CLICKHOUSE_PORT') : null,
    user: getenv('CLICKHOUSE_USER') ?: 'default',
    password: getenv('CLICKHOUSE_PASSWORD') ?: '',
    database: getenv('CLICKHOUSE_DATABASE') ?: 'default',
    ssl: getenv('CLICKHOUSE_SSL') === '1',
));
$connector->connect();

try {
    $raw = $connector->introspect();
    echo "Introspected " . count($raw['tables']) . " tables." . PHP_EOL;

    $schema = (new SchemaGenerator())->generate($raw);

    $client = new Client([
        'base_url' => getenv('EASYSQL_BASE_URL') ?: 'https://api.easysql.net',
        'access_token' => getenv('EASYSQL_ACCESS_TOKEN') ?: '',
    ]);
    $client->syncConnection(['schema' => $schema], getenv('CONNECTION_ID') ?: 'conn_abc123');
    echo "Schema synced." . PHP_EOL;

    $result = $connector->execute('SELECT * FROM events LIMIT 10');
    echo "Fetched {$result['row_count']} rows in {$result['duration_ms']}ms." . PHP_EOL;
} finally {
    $connector->close();
}
