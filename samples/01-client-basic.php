<?php

declare(strict_types=1);

/**
 * Sample 01 — Basic typed client: authenticate, read the current user and list connectors.
 *
 * Requires a valid access token (generate one in the EasySQL panel).
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=easysql_sk_... php samples/01-client-basic.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;

$client = new Client([
    'base_url' => getenv('EASYSQL_BASE_URL') ?: 'https://api.easysql.net',
    'access_token' => getenv('EASYSQL_ACCESS_TOKEN') ?: '',
    'timeout' => 30.0,
]);

// Health is public — no token required.
$health = $client->health();
echo "API health: " . ($health['status'] ?? 'unknown') . PHP_EOL;

// Current user (requires auth).
$user = $client->me();
echo "Signed in as {$user['email']} (plan: " . ($user['active_plan']['name'] ?? 'free') . ")" . PHP_EOL;

// List connectors.
$connectors = $client->listConnectors();
foreach ($connectors['items'] ?? $connectors as $connector) {
    echo "- {$connector['id']} {$connector['name']} ({$connector['type']})" . PHP_EOL;
}
