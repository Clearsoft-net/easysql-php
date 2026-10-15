<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Postgres;

use RuntimeException;

/**
 * One-shot queries over Neon's HTTP SQL endpoint (`POST /sql`).
 *
 * Each call is an independent request, so there is no session state and no
 * persistent connection — ideal for serverless/edge runtimes. The connection
 * string travels in the `Neon-Connection-String` header (never in the URL).
 *
 * This is the "sql-over-http" protocol the Neon serverless driver uses; it is
 * not otherwise a documented public API, so it is exercised only by the
 * env-gated integration test.
 */
final class NeonHttpSession implements PgSession
{
    public function __construct(
        private readonly string $connectionString,
        private readonly string $endpoint,
        private readonly float $timeout = 10.0,
    ) {
    }

    public function open(): void
    {
        // Stateless: HTTP queries do not hold a connection.
    }

    public function query(string $sql, array $params = []): array
    {
        $payload = json_encode(
            ['query' => $sql, 'params' => array_values($params)],
            JSON_THROW_ON_ERROR,
        );

        $ch = curl_init($this->endpoint);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Neon-Connection-String: ' . $this->connectionString,
                // Return values as text, but keep object rows (not array mode).
                'Neon-Raw-Text-Output: true',
            ],
            CURLOPT_TIMEOUT => (int) ceil($this->timeout),
            CURLOPT_CONNECTTIMEOUT => (int) ceil($this->timeout),
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException($error !== '' ? $error : 'curl request failed');
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Unexpected Neon HTTP response');
        }

        if ($status >= 400) {
            $message = $decoded['message'] ?? $decoded['error'] ?? "HTTP {$status}";
            throw new RuntimeException(is_string($message) ? $message : "HTTP {$status}");
        }

        $columns = [];
        foreach ($decoded['fields'] ?? [] as $field) {
            if (is_array($field) && isset($field['name'])) {
                $columns[] = (string) $field['name'];
            }
        }

        $rows = [];
        foreach ($decoded['rows'] ?? [] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    public function close(): void
    {
        // Nothing to release: HTTP queries do not hold a connection.
    }
}
