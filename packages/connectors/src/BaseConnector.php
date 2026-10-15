<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors;

use Clearsoft\EasySQL\Common\SqlValidator;
use RuntimeException;
use Throwable;

/**
 * Shared connector lifecycle.
 *
 * Every engine keeps only what is genuinely driver-specific — how to open and
 * close its handle and how to run a raw query — while this base owns the rest:
 * idempotent `connect`/`close`, the `execute` template (SELECT-only safety
 * check, timing and result assembly) and credential redaction.
 *
 * The TypeScript sibling package keeps two bases (`BaseConnector` async and
 * `SyncBaseConnector`); PHP is synchronous throughout, so one base suffices.
 */
abstract class BaseConnector implements ConnectorInterface
{
    /**
     * The open driver handle (`PDO`, a resource, or an engine marker). `null`
     * means "not connected".
     */
    protected mixed $handle = null;

    /**
     * @param list<string> $secrets Credentials to strip from driver errors.
     */
    protected function __construct(private readonly array $secrets = [])
    {
    }

    final public function connect(): void
    {
        if ($this->handle !== null) {
            return;
        }

        try {
            $this->handle = $this->openHandle();
        } catch (ConnectorException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ConnectorException::connectionFailed($this->engine(), $e, $this->secrets);
        }
    }

    final public function close(): void
    {
        if ($this->handle === null) {
            return;
        }

        $handle = $this->handle;
        $this->handle = null;
        $this->closeHandle($handle);
    }

    final public function execute(string $sql, array $params = []): array
    {
        $handle = $this->requireHandle();

        $validation = SqlValidator::validateSelectOnly($sql);
        if (!$validation['ok']) {
            throw new ConnectorException(
                'SQL safety check failed: ' . ($validation['reason'] ?? 'unknown'),
            );
        }

        $started = (int) (microtime(true) * 1000);

        try {
            return self::buildQueryResult($this->runQuery($handle, $sql, $params), $started);
        } catch (ConnectorException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ConnectorException::executionFailed($this->engine(), $e, $this->secrets);
        }
    }

    /**
     * Opens the underlying driver handle. Called once by {@see connect}; a
     * thrown error is wrapped as a connection failure.
     */
    abstract protected function openHandle(): mixed;

    /**
     * Releases a handle obtained from {@see openHandle}.
     */
    abstract protected function closeHandle(mixed $handle): void;

    /**
     * Runs `sql` and returns the reported columns and raw rows.
     *
     * @param array<string, mixed> $params
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, row_count?: int}
     */
    abstract protected function runQuery(mixed $handle, string $sql, array $params): array;

    /**
     * Human-readable engine name, used in error messages.
     */
    abstract protected function engine(): string;

    /**
     * The open handle, or a "not connected" guard error.
     */
    protected function requireHandle(): mixed
    {
        if ($this->handle === null) {
            throw new RuntimeException(
                $this->engine() . ': not connected — call connect() first.',
            );
        }

        return $this->handle;
    }

    /**
     * Wraps a driver error raised by an engine's {@see introspect}, stripping
     * credentials. A {@see ConnectorException} passes through unchanged.
     */
    protected function wrapIntrospection(Throwable $error): ConnectorException
    {
        if ($error instanceof ConnectorException) {
            return $error;
        }

        return ConnectorException::introspectionFailed($this->engine(), $error, $this->secrets);
    }

    /**
     * @param array{columns: list<string>, rows: list<array<string, mixed>>, row_count?: int} $raw
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, row_count: int, duration_ms: int}
     */
    private static function buildQueryResult(array $raw, int $started): array
    {
        return [
            'columns' => $raw['columns'],
            'rows' => $raw['rows'],
            'row_count' => $raw['row_count'] ?? count($raw['rows']),
            'duration_ms' => (int) (microtime(true) * 1000) - $started,
        ];
    }
}
