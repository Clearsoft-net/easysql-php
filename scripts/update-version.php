<?php

/**
 * Semantic-release prepare step — syncs the release version into the root
 * composer.json and every package composer.json (all packages of the
 * repository are versioned and released together).
 *
 * Usage: php scripts/update-version.php 1.8.0
 */

declare(strict_types=1);

$version = $argv[1] ?? '';
if ($version === '' || !preg_match('/^\d+\.\d+\.\d+/', $version)) {
    fwrite(STDERR, "❌ No valid version provided.\n");
    exit(1);
}

$rootDir = dirname(__DIR__);

$paths = array_merge(
    [$rootDir . '/composer.json'],
    glob($rootDir . '/packages/*/composer.json'),
    glob($rootDir . '/packages/*/*/composer.json'),
);

$written = 0;
foreach ($paths as $path) {
    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json)) {
        fwrite(STDERR, "❌ Invalid JSON in {$path}\n");
        exit(1);
    }
    $json['version'] = $version;
    $encoded = json_encode(
        $json,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    );
    // Match the existing 2-space indentation style
    $encoded = preg_replace_callback(
        '/^ +/m',
        fn ($m) => str_repeat(' ', (int) (strlen($m[0]) / 2)),
        $encoded,
    );
    file_put_contents($path, $encoded . PHP_EOL);
    $written++;
}

fwrite(STDERR, "✅ Version {$version} written to {$written} composer.json files\n");
