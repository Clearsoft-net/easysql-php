<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\LibSql;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\ConnectionConfigInterface;
use InvalidArgumentException;

/**
 * Connection configuration for a local libSQL file or a remote Turso database.
 *
 * libSQL speaks SQLite, so the connector reuses the SQLite catalog for
 * introspection. A local file (`file:...` or a bare path) is opened with PDO
 * and defaults to read-only; a remote `libsql://` / `turso://` / `https://`
 * target is reached over HTTP with an auth token, because every remote
 * statement is an independent request. Credentials are held in memory only.
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    public readonly bool $readOnly;

    public function __construct(
        public readonly string $url = ':memory:',
        public readonly string $authToken = '',
        public readonly float $timeout = 10.0,
        ?bool $readOnly = null,
    ) {
        // Remote databases are authorised per request by their auth token;
        // only a local file has a connection that can be made query-only.
        $this->readOnly = $readOnly ?? self::isFileUrl($this->url);
    }

    /**
     * Builds the config from a `libsql://` / `turso://` / `https://` URL, a
     * `file:` URL, or a bare local path (mirroring SQLite's bare-path form).
     */
    public static function fromUrl(string $url): static
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            throw new InvalidArgumentException('libSQL URL must not be empty.');
        }

        // A bare filesystem path is a local database.
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed) !== 1) {
            return new self(url: $trimmed);
        }

        $scheme = strtolower((string) strstr($trimmed, ':', true));

        // Local file URLs and remote HTTP(S)/WebSocket URLs are kept verbatim;
        // ConnectionUrl only understands the `libsql`/`turso` schemes below.
        if (in_array($scheme, ['file', 'http', 'https', 'ws', 'wss'], true)) {
            return new self(url: $trimmed);
        }

        $parsed = ConnectionUrl::parse($trimmed);
        if ($parsed['type'] !== 'libsql') {
            throw new InvalidArgumentException(
                "Expected a libsql connection string, received '{$parsed['type']}'.",
            );
        }

        return new self(url: $trimmed);
    }

    /** True when this target is a local file rather than a remote server. */
    public function isFile(): bool
    {
        return self::isFileUrl($this->url);
    }

    /**
     * The filesystem path of a local target, stripped of any `file:` prefix.
     */
    public function filePath(): string
    {
        if (stripos($this->url, 'file:') !== 0) {
            return $this->url;
        }

        $path = substr($this->url, strlen('file:'));
        if (str_starts_with($path, '//')) {
            $path = substr($path, 2);
        }

        return $path === '' ? ':memory:' : $path;
    }

    /**
     * Build a PDO DSN for a local target.
     */
    public function toDsn(): string
    {
        return 'sqlite:' . $this->filePath();
    }

    /** True when the URL addresses a local file rather than a remote server. */
    private static function isFileUrl(string $url): bool
    {
        $trimmed = trim($url);

        if (stripos($trimmed, 'file:') === 0) {
            return true;
        }

        // A bare path (no scheme) is local.
        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed) !== 1;
    }
}
