<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\ClickHouse;

use Clearsoft\EasySQL\Connectors\BaseConnector;
use RuntimeException;
use Throwable;

/**
 * ClickHouse connector — local schema introspection and SELECT execution over
 * the ClickHouse HTTP interface.
 *
 * The customer database is introspected locally and only the schema reaches
 * the API, which never stores credentials or executes SQL.
 *
 * No global state: several connections can be open at the same time. The HTTP
 * interface is stateless, so the "handle" is simply a reachability marker.
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
        return 'ClickHouse';
    }

    /**
     * Verifies the HTTP endpoint is reachable via the `/ping` handler.
     */
    protected function openHandle(): bool
    {
        $ch = curl_init($this->config->baseUrl() . '/ping');
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }

        curl_setopt_array($ch, $this->curlOptions() + [CURLOPT_HTTPGET => true]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status >= 400) {
            throw new RuntimeException($error !== '' ? $error : "HTTP {$status}");
        }

        return true;
    }

    protected function closeHandle(mixed $handle): void
    {
        // The HTTP interface is stateless — nothing to release.
    }

    protected function runQuery(mixed $handle, string $sql, array $params): array
    {
        $body = $this->request($sql, [], 'JSON');
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Unexpected ClickHouse response');
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
        ];
    }

    /**
     * Introspect the configured database.
     *
     * Returns the raw introspection shape consumed by the schema-generation
     * package: ['engine' => 'clickhouse', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $this->requireHandle();

        try {
            $tableRows = $this->queryJsonEachRow(Queries::TABLES);

            $tables = [];
            foreach ($tableRows as $table) {
                $columnRows = $this->queryJsonEachRow(
                    Queries::COLUMNS,
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
        } catch (Throwable $e) {
            throw $this->wrapIntrospection($e);
        }
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
     * Send a query to the ClickHouse HTTP interface. Credentials in errors are
     * stripped by the caller's error wrapping, not here.
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
            throw new RuntimeException($error !== '' ? $error : 'curl request failed');
        }

        if ($status >= 400) {
            $message = trim((string) $body) !== '' ? trim((string) $body) : "HTTP {$status}";
            throw new RuntimeException($message);
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

    private function toBool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }
}
