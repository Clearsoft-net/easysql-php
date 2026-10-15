<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors;

/**
 * A database connector: opens/closes a connection, reads local metadata and
 * executes validated SQL. Credentials never leave the process.
 *
 * Mirrors the `Connector` contract of the TypeScript sibling package
 * (`@easysql/common`).
 */
interface ConnectorInterface
{
    /**
     * Opens the connection. Idempotent — calling twice keeps the same handle.
     */
    public function connect(): void;

    /**
     * Closes the connection and releases all resources. Safe to call when not
     * connected.
     */
    public function close(): void;

    /**
     * Reads local metadata only — never returns rows.
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array;

    /**
     * Executes a validated SELECT statement and returns typed rows.
     *
     * @param array<string, mixed> $params Bound parameters for prepared statements.
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, row_count: int, duration_ms: int}
     */
    public function execute(string $sql, array $params = []): array;
}
