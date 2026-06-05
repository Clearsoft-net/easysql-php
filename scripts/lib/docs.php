<?php

/**
 * Documentation generation — produces docs/API.md grouped by category.
 */

namespace Clearsoft\EasySQL\SDK\Scripts;

/**
 * Generate a markdown API reference from the extracted methods.
 */
function generateDocs(array $methods, string $outputPath): void
{
    $lines = [];

    $lines[] = '# API Reference';
    $lines[] = '';
    $lines[] = '> Auto-generated from the OpenAPI spec. Do not edit manually.';
    $lines[] = '';

    // Group by tag
    $groups = [];
    foreach ($methods as $m) {
        $groups[$m->tag][] = $m;
    }

    // Sort groups alphabetically
    ksort($groups);

    // Table of contents
    $lines[] = '## Endpoints';
    $lines[] = '';
    foreach ($groups as $tag => $groupMethods) {
        $label = ucfirst($tag);
        $anchor = strtolower($tag);
        $lines[] = "- [{$label}](#{$anchor})";
    }
    $lines[] = '';

    // Per-group sections
    foreach ($groups as $tag => $groupMethods) {
        $label = ucfirst($tag);
        $lines[] = "## {$label}";
        $lines[] = '';

        foreach ($groupMethods as $m) {
            $authBadge = $m->requiresAuth ? ' 🔒' : '';
            $lines[] = "### `{$m->name}()`{$authBadge}";
            $lines[] = '';
            $lines[] = "{$m->summary}.";
            $lines[] = '';
            $lines[] = "```";
            $lines[] = "{$m->httpMethod} {$m->path}";
            $lines[] = "```";
            $lines[] = '';

            // Parameters
            $hasParams = !empty($m->pathParams) || !empty($m->queryParams) || $m->hasBody;
            if ($hasParams) {
                $lines[] = '**Parameters:**';
                $lines[] = '';
                if ($m->hasBody && $m->requestSchema) {
                    $lines[] = "- `body` — `{$m->requestSchema}`";
                }
                foreach ($m->pathParams as $p) {
                    $req = $p['required'] ? 'required' : 'optional';
                    $format = $p['format'] ? " ({$p['format']})" : '';
                    $lines[] = "- `{$p['name']}` — `{$p['type']}{$format}` ({$req}, path)";
                }
                foreach ($m->queryParams as $p) {
                    $req = $p['required'] ? 'required' : 'optional';
                    $lines[] = "- `{$p['name']}` — `{$p['type']}` ({$req}, query)";
                }
                $lines[] = '';
            }

            // Response
            if ($m->returnsNothing) {
                $lines[] = '**Returns:** `204 No Content`';
            } elseif ($m->responseSchema) {
                $lines[] = "**Returns:** `{$m->responseSchema}`";
            }
            $lines[] = '';
            $lines[] = '---';
            $lines[] = '';
        }
    }

    $dir = dirname($outputPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($outputPath, implode("\n", $lines) . "\n");
}
