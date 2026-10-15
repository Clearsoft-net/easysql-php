<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors;

/**
 * A connection config that can be built from a connection URL/DSN string.
 *
 * The parsing itself lives in {@see \Clearsoft\EasySQL\Common\ConnectionUrl};
 * each engine maps the parsed generic parts onto its own config fields (for
 * example PostgreSQL derives `sslmode` from the parsed `ssl` flag). This is the
 * PHP analog of the TypeScript `normalize` step shared by the engine `create()`
 * factories.
 */
interface ConnectionConfigInterface
{
    /**
     * Builds the engine config from an engine-native URL/DSN (or, for the
     * file-backed engines, a bare file path).
     *
     * @throws \InvalidArgumentException When the URL targets another engine.
     */
    public static function fromUrl(string $url): static;
}
