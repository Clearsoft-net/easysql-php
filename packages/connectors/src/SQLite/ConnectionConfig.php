<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\SQLite;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;

/**
 * Connection configuration for a SQLite database.
 *
 * SQLite lives on the same machine as the client — the file path is local
 * metadata and is never sent to the API (only the schema payload is).
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    public function __construct(
        public readonly string $path = ':memory:',
        public readonly bool $readOnly = false,
        public readonly float $timeout = 10.0,
    ) {
    }

    /**
     * Builds the config from a bare file path, `:memory:`, or a
     * `sqlite:///path/to.db` URL.
     */
    public static function fromUrl(string $url): static
    {
        $path = str_starts_with($url, 'sqlite:')
            ? ConnectionUrl::parse($url)['database']
            : $url;

        return new self(path: $path);
    }

    /**
     * Build a PDO DSN for this configuration.
     */
    public function toDsn(): string
    {
        return 'sqlite:' . $this->path;
    }
}
