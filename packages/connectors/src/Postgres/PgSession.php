<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Postgres;

/**
 * A transport session for the PostgreSQL family.
 *
 * The connector talks to this small surface, so the transport (`tcp` over the
 * Postgres wire protocol, or Neon over HTTPS) stays interchangeable while
 * introspection and execution logic stay shared — mirroring the `PgSession`
 * abstraction of the TypeScript sibling package.
 */
interface PgSession
{
    /**
     * Opens the session. Idempotent. For stateless transports this is a no-op.
     *
     * @throws \Throwable When the session cannot be established.
     */
    public function open(): void;

    /**
     * Runs `sql` with bound parameters and returns its columns and rows.
     *
     * @param array<string, mixed>|list<mixed> $params
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    public function query(string $sql, array $params = []): array;

    /**
     * Releases the session. Safe to call when not open.
     */
    public function close(): void;
}
