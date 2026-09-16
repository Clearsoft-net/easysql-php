<?php

declare(strict_types=1);

/**
 * Sample 14 — Typed DTOs: hydrate API responses into the generated Models.
 *
 * The client returns plain arrays; the `Clearsoft\EasySQL\Client\Models\*`
 * classes provide typed, documented access via `fromArray()`.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=... CONNECTOR_ID=conn_... php samples/14-typed-dtos.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;
use Clearsoft\EasySQL\Client\Models\ConnectorResponse;
use Clearsoft\EasySQL\Client\Models\TokenResponse;
use Clearsoft\EasySQL\Client\Models\UserResponse;

$client = new Client([
    'base_url' => getenv('EASYSQL_BASE_URL') ?: 'https://api.easysql.net',
    'access_token' => getenv('EASYSQL_ACCESS_TOKEN') ?: '',
]);

// Auth tokens as a typed object.
$tokens = TokenResponse::fromArray($client->refresh([
    'refresh_token' => getenv('EASYSQL_REFRESH_TOKEN') ?: '',
]));
echo "Bearer type: {$tokens->token_type}" . PHP_EOL;

// Current user.
$user = UserResponse::fromArray($client->me());
echo "User: {$user->email} (id: {$user->id})" . PHP_EOL;

// A connector.
$connector = ConnectorResponse::fromArray(
    $client->getConnector(getenv('CONNECTOR_ID') ?: 'conn_abc123'),
);
echo "Connector: {$connector->name} [{$connector->type}] last sync: {$connector->last_sync_at}" . PHP_EOL;

// Hydration is tolerant: missing keys fall back to type defaults.
$empty = ConnectorResponse::fromArray([]);
echo "Empty hydration -> name: '{$empty->name}', type: '{$empty->type}'" . PHP_EOL;
