<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Trino;

use Clearsoft\EasySQL\Connectors\BaseConnector;
use RuntimeException;
use Throwable;

/**
 * Trino connector — local schema introspection and SELECT execution over the
 * Trino REST protocol.
 *
 * The customer database is introspected locally and only the schema reaches
 * the API, which never stores credentials or executes SQL.
 *
 * The protocol is HTTP: a statement is submitted with a `POST` to
 * `/v1/statement` and the coordinator returns one or more pages linked by
 * `nextUri`, which are followed until the query completes. There is no
 * long-lived socket, so the "handle" is a reachability marker.
 */
final class Connector extends BaseConnector
{
    public function __construct(private readonly ConnectionConfig $config)
    {
        parent::__construct(array_filter(
            [$config->password],
            static fn (string $secret): bool => $secret !== '',
        ));
    }

    protected function engine(): string
    {
        return 'Trino';
    }

    /**
     * Probes the coordinator with a cheap statement so an unreachable server or
     * bad credentials surfaces at connect time.
     */
    protected function openHandle(): bool
    {
        $this->collect('SELECT 1');

        return true;
    }

    protected function closeHandle(mixed $handle): void
    {
        // The REST protocol is stateless — nothing to release.
    }

    protected function runQuery(mixed $handle, string $sql, array $params): array
    {
        // The Trino statement endpoint takes the SQL as its raw body and has no
        // bound-parameter channel, so `$params` is intentionally unused.
        $page = $this->collect($sql);

        return ['columns' => $page['columns'], 'rows' => $page['rows']];
    }

    /**
     * Introspect the configured catalog/schema.
     *
     * Trino does not expose primary/foreign-key metadata through
     * `information_schema` in the general case, so every column is reported
     * with `primaryKey: false` and `foreignKey: null`, and `rowsApprox` is
     * unavailable (`null`). Downstream consumers must not treat those fields as
     * authoritative for this engine.
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $this->requireHandle();

        try {
            $tablePage = $this->collect(Queries::TABLES);

            $tables = [];
            foreach ($tablePage['rows'] as $tableRow) {
                $name = (string) ($tableRow['table_name'] ?? '');

                $columnsPage = $this->collect(Queries::columns($name));

                $columns = [];
                foreach ($columnsPage['rows'] as $column) {
                    $columns[] = [
                        'name' => (string) ($column['column_name'] ?? ''),
                        'dataType' => (string) ($column['data_type'] ?? ''),
                        // information_schema reports the string "YES"/"NO".
                        'nullable' => strtoupper((string) ($column['is_nullable'] ?? '')) === 'YES',
                        // Trino exposes no key metadata through information_schema.
                        'primaryKey' => false,
                        'defaultValue' => $column['column_default'] ?? null,
                        'foreignKey' => null,
                        'ordinal' => (int) ($column['ordinal_position'] ?? 0),
                    ];
                }

                $tables[] = [
                    'name' => $name,
                    'columns' => $columns,
                    // Trino exposes no row-count estimate through information_schema.
                    'rowsApprox' => null,
                ];
            }

            return ['engine' => 'trino', 'tables' => $tables];
        } catch (Throwable $e) {
            throw $this->wrapIntrospection($e);
        }
    }

    /**
     * Submits one statement and drains every page.
     *
     * The initial `POST /v1/statement` response may already carry `data` and
     * `columns`, so those are collected before following `nextUri` until the
     * coordinator stops returning one. The last non-empty `columns` page wins.
     *
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function collect(string $sql): array
    {
        $columns = [];
        $rows = [];

        $body = $this->request('POST', $this->config->baseUrl() . '/v1/statement', $sql);

        for (;;) {
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Unexpected Trino response');
            }

            if (isset($decoded['error']) && is_array($decoded['error'])) {
                $error = $decoded['error'];
                $name = isset($error['errorName']) ? (string) $error['errorName'] : 'TrinoQueryError';
                $message = isset($error['message']) ? (string) $error['message'] : 'query failed';
                throw new RuntimeException($name . ': ' . $message);
            }

            if (isset($decoded['columns']) && is_array($decoded['columns']) && $decoded['columns'] !== []) {
                $columns = [];
                foreach ($decoded['columns'] as $column) {
                    if (is_array($column)) {
                        $columns[] = $this->shortName((string) ($column['name'] ?? ''));
                    }
                }
            }

            if (isset($decoded['data']) && is_array($decoded['data'])) {
                foreach ($decoded['data'] as $row) {
                    $rows[] = $this->toRow($row, $columns);
                }
            }

            $next = $decoded['nextUri'] ?? null;
            if (!is_string($next) || $next === '') {
                break;
            }

            $body = $this->request('GET', $this->absolute($next), null);
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * Maps one Trino data row onto the collected columns. Trino returns rows as
     * positional arrays; an object row is passed through unchanged.
     *
     * @param list<string> $columns
     * @return array<string, mixed>
     */
    private function toRow(mixed $row, array $columns): array
    {
        if (!is_array($row)) {
            return [];
        }

        if (array_is_list($row)) {
            $out = [];
            foreach ($columns as $index => $column) {
                $out[$column] = $row[$index] ?? null;
            }

            return $out;
        }

        /** @var array<string, mixed> $row */
        return $row;
    }

    /** Trino returns names as `table.column`; keep the column part. */
    private function shortName(string $name): string
    {
        $dot = strrpos($name, '.');

        return $dot === false ? $name : substr($name, $dot + 1);
    }

    /** Resolves a `nextUri` that may be absolute or coordinator-relative. */
    private function absolute(string $uri): string
    {
        if (preg_match('#^https?://#i', $uri) === 1) {
            return $uri;
        }

        return rtrim($this->config->baseUrl(), '/') . '/' . ltrim($uri, '/');
    }

    /**
     * Sends one request to the coordinator. Credentials in errors are stripped
     * by the caller's error wrapping, not here.
     */
    private function request(string $method, string $url, ?string $body): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }

        $options = $this->curlOptions();
        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body ?? '';
        } else {
            $options[CURLOPT_HTTPGET] = true;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException($error !== '' ? $error : 'curl request failed');
        }

        if ($status >= 400) {
            $message = trim((string) $response) !== '' ? trim((string) $response) : "HTTP {$status}";
            throw new RuntimeException($message);
        }

        return (string) $response;
    }

    /**
     * @return array<int, mixed>
     */
    private function curlOptions(): array
    {
        $headers = [
            'X-Trino-User: ' . $this->config->user,
            'Content-Type: text/plain; charset=utf-8',
        ];

        if ($this->config->password !== '') {
            $headers[] = 'Authorization: Basic '
                . base64_encode($this->config->user . ':' . $this->config->password);
        }
        if ($this->config->catalog !== '') {
            $headers[] = 'X-Trino-Catalog: ' . $this->config->catalog;
        }
        if ($this->config->schema !== '') {
            $headers[] = 'X-Trino-Schema: ' . $this->config->schema;
        }

        return [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => (int) ceil($this->config->timeout),
            CURLOPT_CONNECTTIMEOUT => (int) ceil($this->config->timeout),
        ];
    }
}
