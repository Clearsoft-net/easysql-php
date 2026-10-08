<?php

declare(strict_types=1);

/**
 * Sample 14 — Typed DTOs: hydrate API responses into the generated Models.
 *
 * The client returns plain arrays; the `Clearsoft\EasySQL\Client\Models\*`
 * classes provide typed, documented access via `fromArray()`.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=... CONNECTION_ID=conn_... php samples/14-typed-dtos.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;
use Clearsoft\EasySQL\Client\Models\ConnectionResponse;
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

// A connection.
$connection = ConnectionResponse::fromArray(
    $client->getConnection(getenv('CONNECTION_ID') ?: 'conn_abc123'),
);
echo "Connection: {$connection->name} [{$connection->type}] last sync: {$connection->last_sync_at}" . PHP_EOL;

// Hydration is tolerant: missing keys fall back to type defaults.
$empty = ConnectionResponse::fromArray([]);
echo "Empty hydration -> name: '{$empty->name}', type: '{$empty->type}'" . PHP_EOL;
