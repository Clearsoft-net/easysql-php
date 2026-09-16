<?php

declare(strict_types=1);

/**
 * Sample 07 — PostgreSQL: introspect locally, generate the schema payload and sync it.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=... CONNECTOR_ID=conn_... \
 *   PG_HOST=127.0.0.1 PG_USER=postgres PG_PASSWORD=postgres PG_DATABASE=shop \
 *   php samples/07-conn-postgres-sync.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;
use Clearsoft\EasySQL\Connectors\Postgres\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\Postgres\Connector as PostgresConnector;
use Clearsoft\EasySQL\SchemaGeneration\SchemaGenerator;

$connector = new PostgresConnector(new ConnectionConfig(
    host: getenv('PG_HOST') ?: '127.0.0.1',
    port: (int) (getenv('PG_PORT') ?: 5432),
    user: getenv('PG_USER') ?: 'postgres',
    password: getenv('PG_PASSWORD') ?: '',
    database: getenv('PG_DATABASE') ?: 'postgres',
    sslmode: getenv('PG_SSLMODE') ?: 'prefer',
    schemas: ['public'],
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

    $result = $connector->execute('SELECT id, email FROM customers LIMIT 10');
    echo "Fetched {$result['row_count']} rows in {$result['duration_ms']}ms." . PHP_EOL;
} finally {
    $connector->close();
}
