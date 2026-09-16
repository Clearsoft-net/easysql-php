<?php

declare(strict_types=1);

/**
 * Sample 08 — SQLite: introspect a local file (read-only), generate and sync.
 *
 * SQLite needs no server; this sample also demonstrates the read-only mode.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=... CONNECTOR_ID=conn_... SQLITE_PATH=/path/app.db \
 *   php samples/08-conn-sqlite-sync.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;
use Clearsoft\EasySQL\Connectors\SQLite\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\SQLite\Connector as SQLiteConnector;
use Clearsoft\EasySQL\SchemaGeneration\SchemaGenerator;

$connector = new SQLiteConnector(new ConnectionConfig(
    path: getenv('SQLITE_PATH') ?: ':memory:',
    readOnly: true,
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
    $client->syncConnector(['schema' => $schema], getenv('CONNECTOR_ID') ?: 'conn_abc123');
    echo "Schema synced." . PHP_EOL;

    $result = $connector->execute('SELECT * FROM sqlite_master LIMIT 5');
    echo "Fetched {$result['row_count']} rows in {$result['duration_ms']}ms." . PHP_EOL;
} finally {
    $connector->close();
}
