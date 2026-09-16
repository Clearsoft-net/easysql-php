<?php

declare(strict_types=1);

/**
 * Sample 05 — Dashboard, plan, usage and billing.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=... php samples/05-dashboard-billing.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;

$client = new Client([
    'base_url' => getenv('EASYSQL_BASE_URL') ?: 'https://api.easysql.net',
    'access_token' => getenv('EASYSQL_ACCESS_TOKEN') ?: '',
]);

// Usage dashboard.
$stats = $client->dashboardStats();
echo "Queries today: " . ($stats['queries_today'] ?? 0) . PHP_EOL;

// Plan + usage.
$plan = $client->getPlan();
echo "Plan: " . ($plan['name'] ?? 'free') . PHP_EOL;

$usage = $client->getUsage();
echo "Monthly queries: " . ($usage['queries_count'] ?? 0) . PHP_EOL;

// Billing checkout — query strategy takes an array.
$checkout = $client->checkout(['plan' => 'pro', 'interval' => 'monthly']);
echo "Checkout URL: " . ($checkout['checkout_url'] ?? 'n/a') . PHP_EOL;

// Customer billing portal.
$portal = $client->portal();
echo "Billing portal: " . ($portal['portal_url'] ?? 'n/a') . PHP_EOL;
