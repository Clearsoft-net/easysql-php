<?php

declare(strict_types=1);

/**
 * Sample 10 — Laravel integration (service provider, manager, facade).
 *
 * Paste this inside a Laravel app after `composer require clearsoft/easysql-laravel`
 * and publishing the config:
 *
 *   php artisan vendor:publish --tag=easysql-config
 *
 * .env:
 *   EASYSQL_BASE_URL=https://api.easysql.net
 *   EASYSQL_ACCESS_TOKEN=your-access-token
 */

use Clearsoft\EasySql\Laravel\Facades\EasySQL;
use Clearsoft\EasySql\Laravel\EasySqlManager;

// Through the facade.
$user = EasySQL::me();
echo "Signed in as {$user['email']}" . PHP_EOL;

$result = EasySQL::createQuery([
    'connector_id' => 'conn_abc123',
    'question' => 'How many users signed up last month?',
]);
echo "Query {$result['id']} created." . PHP_EOL;

// Through the container / manager (default connection).
/** @var EasySqlManager $manager */
$manager = app(EasySqlManager::class);
$stats = $manager->dashboardStats();
echo "Queries today: " . ($stats['queries_today'] ?? 0) . PHP_EOL;

// Named connections defined in config/easysql.php.
// Add e.g. 'analytics' under "connections" and switch:
$analytics = EasySQL::connection('analytics');
// $analytics->listConnectors();
echo "Default connection: {$manager->getDefaultConnection()}" . PHP_EOL;
