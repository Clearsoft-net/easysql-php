<?php

declare(strict_types=1);

/**
 * Sample 11 — Error handling: ApiException for HTTP errors, Guzzle for transport errors.
 *
 * The client throws ApiException on any response >= 400; transport failures
 * surface as Guzzle exceptions.
 *
 * Run:
 *   EASYSQL_ACCESS_TOKEN=... php samples/11-error-handling.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Clearsoft\EasySQL\Client\Client;
use Clearsoft\EasySQL\Client\Exceptions\ApiException;
use GuzzleHttp\Exception\GuzzleException;

$client = new Client([
    'base_url' => getenv('EASYSQL_BASE_URL') ?: 'https://api.easysql.net',
    'access_token' => getenv('EASYSQL_ACCESS_TOKEN') ?: '',
]);

try {
    // A connector id that does not exist -> 404.
    $client->getConnector('conn_does_not_exist');
} catch (ApiException $e) {
    echo "HTTP {$e->getStatusCode()}: {$e->getMessage()}" . PHP_EOL;

    // Decoded JSON error body (FastAPI-style `detail`, or `message`).
    $details = $e->getErrorDetails();
    if (isset($details['detail'])) {
        echo "Detail: " . json_encode($details['detail']) . PHP_EOL;
    }

    if ($e->getStatusCode() === 401) {
        echo "Unauthorized — refresh the token or check the API key." . PHP_EOL;
    } elseif ($e->getStatusCode() === 429) {
        echo "Rate limited — retry after the current window." . PHP_EOL;
    }
} catch (GuzzleException $e) {
    // DNS failure, timeout, TLS error, ...
    echo "Network error: {$e->getMessage()}" . PHP_EOL;
}

// A reusable pattern for callers that want a result instead of an exception.
function safe(callable $fn): mixed
{
    try {
        return $fn();
    } catch (ApiException|GuzzleException $e) {
        error_log("[easysql] " . $e->getMessage());

        return null;
    }
}

$user = safe(fn () => $client->me());
echo $user === null ? "Could not load the current user." . PHP_EOL : "Loaded {$user['email']}" . PHP_EOL;
