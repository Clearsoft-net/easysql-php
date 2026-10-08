<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\ClickHouse;

use Clearsoft\EasySQL\Common\CredentialSanitizer;
use Clearsoft\EasySQL\Common\SqlValidator;
use RuntimeException;

/**
 * ClickHouse connector — local schema introspection and SELECT execution over
 * the ClickHouse HTTP interface.
 *
 * The customer database is introspected locally and only the schema reaches
 * the API, which never stores credentials or executes SQL.
 *
 * No global state: several connections can be open at the same time.
 */
final class Connector
{
    private bool $connected = false;

    public function __construct(private readonly ConnectionConfig $config)
    {
    }

    /**
     * Open the connection. Idempotent — calling twice keeps the same connection.
     *
     * Verifies the HTTP endpoint is reachable via the `/ping` handler.
     *
     * @throws ConnectorException When the connection cannot be established.
     */
    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $ch = curl_init($this->config->baseUrl() . '/ping');
        if ($ch === false) {
            throw ConnectorException::connectionFailed(new RuntimeException('curl_init failed'));
        }

        curl_setopt_array($ch, $this->curlOptions() + [CURLOPT_HTTPGET => true]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status >= 400) {
            $message = $error !== '' ? $error : "HTTP {$status}";
            throw ConnectorException::connectionFailed(new RuntimeException($message));
        }

        $this->connected = true;
    }

    /**
     * Close the connection and release all resources.
     */
    public function close(): void
    {
        $this->connected = false;
    }

    /**
     * Introspect the configured database.
     *
     * Returns the raw introspection shape consumed by the schema-generation
     * package: ['engine' => 'clickhouse', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     *
     * @throws ConnectorException When introspection fails.
     */
    public function introspect(): array
    {
        $this->requireConnection();

        try {
            $tableRows = $this->queryJsonEachRow(
                "SELECT name AS table_name, total_rows AS rows_approx
                 FROM system.tables
                 WHERE database = currentDatabase()
                   AND engine NOT LIKE '%View'
                 ORDER BY name",
            );

            $tables = [];
            foreach ($tableRows as $table) {
                $columnRows = $this->queryJsonEachRow(
                    "SELECT name AS column_name, type AS data_type,
                            default_expression AS column_default,
                            is_in_primary_key AS is_primary, position AS ordinal
                     FROM system.columns
                     WHERE database = currentDatabase() AND table = {table:String}
                     ORDER BY position",
                    ['table' => (string) $table['table_name']],
                );

                $columns = [];
                foreach ($columnRows as $column) {
                    $dataType = (string) $column['data_type'];
                    $columns[] = [
                        'name' => $column['column_name'],
                        'dataType' => $dataType,
                        'nullable' => str_starts_with($dataType, 'Nullable('),
                        'primaryKey' => $this->toBool($column['is_primary']),
                        'defaultValue' => ($column['column_default'] ?? '') !== ''
                            ? (string) $column['column_default']
                            : null,
                        'foreignKey' => null,
                        'ordinal' => (int) $column['ordinal'],
                    ];
                }

                $rowsApprox = $table['rows_approx'] ?? null;
                $tables[] = [
                    'name' => $table['table_name'],
                    'columns' => $columns,
                    'rowsApprox' => $rowsApprox === null ? null : (int) $rowsApprox,
                ];
            }

            return ['engine' => 'clickhouse', 'tables' => $tables];
        } catch (RuntimeException $e) {
            if ($e instanceof ConnectorException) {
                throw $e;
            }
            throw ConnectorException::introspectionFailed($e);
        }
    }

    /**
     * Execute a generated SELECT statement and return typed rows.
     *
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, row_count: int, duration_ms: int}
     * @throws ConnectorException When execution fails or the SQL is unsafe.
     */
    public function execute(string $sql): array
    {
        $this->requireConnection();

        $validation = SqlValidator::validateSelectOnly($sql);
        if (!$validation['ok']) {
            throw new ConnectorException(
                'SQL safety check failed: ' . ($validation['reason'] ?? 'unknown'),
            );
        }

        $started = (int) (microtime(true) * 1000);

        try {
            $body = $this->request($sql, [], 'JSON');
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Unexpected ClickHouse response');
            }
        } catch (RuntimeException $e) {
            if ($e instanceof ConnectorException) {
                throw $e;
            }
            throw ConnectorException::executionFailed($e);
        }

        $columns = [];
        foreach ($decoded['meta'] ?? [] as $meta) {
            $columns[] = (string) $meta['name'];
        }

        $rows = [];
        foreach ($decoded['data'] ?? [] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'row_count' => isset($decoded['rows']) ? (int) $decoded['rows'] : count($rows),
            'duration_ms' => (int) (microtime(true) * 1000) - $started,
        ];
    }

    /**
     * @param array<string, string> $params
     * @return list<array<string, mixed>>
     */
    private function queryJsonEachRow(string $sql, array $params = []): array
    {
        $body = $this->request($sql, $params, 'JSONEachRow');

        $rows = [];
        foreach (explode("\n", trim($body)) as $line) {
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }

    /**
     * Send a query to the ClickHouse HTTP interface.
     *
     * @param array<string, string> $params Bound `{name:Type}` query parameters.
     */
    private function request(string $sql, array $params, string $format): string
    {
        $query = ['database' => $this->config->database, 'default_format' => $format];
        foreach ($params as $name => $value) {
            $query['param_' . $name] = $value;
        }

        $url = $this->config->baseUrl() . '/?' . http_build_query($query);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }

        curl_setopt_array($ch, $this->curlOptions() + [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $sql,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            $message = $error !== '' ? $error : 'curl request failed';
            throw ConnectorException::executionFailed(
                new RuntimeException(CredentialSanitizer::sanitize($message)),
            );
        }

        if ($status >= 400) {
            $message = trim((string) $body) !== '' ? trim((string) $body) : "HTTP {$status}";
            throw ConnectorException::executionFailed(
                new RuntimeException(CredentialSanitizer::sanitize($message)),
            );
        }

        return (string) $body;
    }

    /**
     * @return array<int, mixed>
     */
    private function curlOptions(): array
    {
        return [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-ClickHouse-User: ' . $this->config->user,
                'X-ClickHouse-Key: ' . $this->config->password,
            ],
            CURLOPT_TIMEOUT => (int) ceil($this->config->timeout),
            CURLOPT_CONNECTTIMEOUT => (int) ceil($this->config->timeout),
        ];
    }

    private function requireConnection(): void
    {
        if (!$this->connected) {
            throw new RuntimeException('Not connected — call connect() first.');
        }
    }

    private function toBool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }
}
