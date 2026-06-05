#!/usr/bin/env php
<?php
/**
 * Code generation orchestrator for the EasySQL PHP SDK.
 *
 * Usage:
 *   php scripts/generate.php [--spec-url=https://api.easysql.net/openapi.json]
 *
 * Environment:
 *   EASYSQL_API_URL — base URL or full path to the OpenAPI spec.
 *     Default: http://localhost:8000/openapi.json
 *
 * Reads the OpenAPI spec and generates:
 *   - src/Client.php        (named API methods)
 *   - src/Models/*.php       (request/response DTOs)
 *   - docs/API.md            (markdown reference)
 */

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────

$rootDir = dirname(__DIR__);

// Composer autoloader (for Guzzle etc. if needed by the script itself)
$autoloadPaths = [
    $rootDir . "/vendor/autoload.php",
    $rootDir . "/../../autoload.php", // when installed as a dependency
];

foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        break;
    }
}

// Manual require of our lib files
require_once __DIR__ . "/lib/extract.php";
require_once __DIR__ . "/lib/build.php";
require_once __DIR__ . "/lib/docs.php";

use function Clearsoft\EasySQL\SDK\Scripts\downloadSpec;
use function Clearsoft\EasySQL\SDK\Scripts\extractMethods;
use function Clearsoft\EasySQL\SDK\Scripts\buildInterface;
use function Clearsoft\EasySQL\SDK\Scripts\buildImpl;
use function Clearsoft\EasySQL\SDK\Scripts\generateModels;
use function Clearsoft\EasySQL\SDK\Scripts\generateDocs;

// ── CLI argument parsing ─────────────────────────────────────

$specUrl = getenv("EASYSQL_API_URL") ?: "http://localhost:8000/openapi.json";

$args = array_slice($argv, 1);
foreach ($args as $arg) {
    if (str_starts_with($arg, "--spec-url=")) {
        $specUrl = substr($arg, strlen("--spec-url="));
    }
}

// If env var is a base URL (without /openapi.json), append the path
if (
    !str_contains($specUrl, "openapi.json") &&
    !str_ends_with($specUrl, ".json")
) {
    $specUrl = rtrim($specUrl, "/") . "/openapi.json";
}

fwrite(STDERR, "📥 Downloading spec from {$specUrl}...\n");

try {
    $spec = downloadSpec($specUrl);
} catch (\RuntimeException $e) {
    fwrite(STDERR, "❌ {$e->getMessage()}\n");
    exit(1);
}

// ── Extract methods ──────────────────────────────────────────

fwrite(STDERR, "🔍 Parsing endpoints...\n");
$methods = extractMethods($spec);

fwrite(STDERR, "   Found " . count($methods) . " operations.\n");

// ── Generate Client.php ──────────────────────────────────────

$template = file_get_contents(__DIR__ . "/client.php.tpl");

$implementation = buildImpl($methods);

$code = str_replace("{{IMPLEMENTATION}}", $implementation, $template);

$clientPath = $rootDir . "/src/Client.php";
file_put_contents($clientPath, $code);
fwrite(STDERR, "✅ Generated src/Client.php\n");

// ── Generate Models ──────────────────────────────────────────

$modelsDir = $rootDir . "/src/Models";
$modelNames = generateModels($spec, $modelsDir);
fwrite(
    STDERR,
    "✅ Generated " . count($modelNames) . " models in src/Models/\n",
);

// ── Generate docs ────────────────────────────────────────────

$docsPath = $rootDir . "/docs/API.md";
generateDocs($methods, $docsPath);
fwrite(STDERR, "✅ Generated docs/API.md\n");

// ── Summary ──────────────────────────────────────────────────

fwrite(STDERR, "\n🎉 Generation complete!\n");

