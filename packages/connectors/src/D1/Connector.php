<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\D1;

use Clearsoft\EasySQL\Connectors\BaseConnector;
use RuntimeException;
use Throwable;

/**
 * Cloudflare D1 connector — schema introspection and SELECT execution.
 *
 * There is no PHP driver and no persistent socket: every statement is a
 * `POST /accounts/{accountId}/d1/database/{databaseId}/query` request against
 * Cloudflare's REST API. {@see connect} therefore only validates the
 * configuration and proves reachability with a cheap `SELECT 1`; {@see close}
 * just clears that state. The API token is stripped from every error message
 * that leaves the process.
 *
 * Introspection uses the SQLite catalog exactly like the SQLite connector.
 * Cloudflare documents `PRAGMA table_info("TABLE_NAME")` and
 * `PRAGMA foreign_key_list("TABLE_NAME")` as compatible statements for D1, so
 * the PRAGMA form is preferred; the table-valued equivalents are used as a
 * fallback when the endpoint rejects the PRAGMA statement, and the working form
 * is remembered for the rest of the connection.
 */
final class Connector extends BaseConnector
{
    /** Null until a PRAGMA statement has been attempted on this connection. */
    private ?bool $pragmaSupported = null;

    public function __construct(private readonly ConnectionConfig $config)
    {
        parent::__construct(array_filter(
            [$config->apiToken],
            static fn (string $secret): bool => $secret !== '',
        ));
    }

    protected function engine(): string
    {
        return 'D1';
    }

    /**
     * Validates the configuration and verifies the database is reachable.
     */
    protected function openHandle(): bool
    {
        // A cheap round-trip proves the account, database and token all work.
        $this->request('SELECT 1', []);

        return true;
    }

    protected function closeHandle(mixed $handle): void
    {
        $this->pragmaSupported = null;
    }

    protected function runQuery(mixed $handle, string $sql, array $params): array
    {
        $body = $this->request($sql, $params);

        $result = $body['result'] ?? null;
        $first = is_array($result) && isset($result[0]) && is_array($result[0]) ? $result[0] : null;

        $rows = [];
        if ($first !== null && isset($first['results']) && is_array($first['results'])) {
            foreach ($first['results'] as $row) {
                if (is_array($row)) {
                    $rows[] = $this->assoc($row);
                }
            }
        }

        $columns = $rows === [] ? [] : array_keys($rows[0]);

        $rowCount = count($rows);
        if ($rowCount === 0 && $first !== null && isset($first['meta']) && is_array($first['meta'])) {
            $changes = $first['meta']['changes'] ?? $first['meta']['rows_written'] ?? 0;
            $rowCount = is_numeric($changes) ? (int) $changes : 0;
        }

        return ['columns' => $columns, 'rows' => $rows, 'row_count' => $rowCount];
    }

    /**
     * Introspect the database schema.
     *
     * Returns the raw introspection shape consumed by the schema-generation
     * package: ['engine' => 'd1', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $this->requireHandle();

        try {
            $tableRows = $this->selectRows(Queries::TABLES);

            $tables = [];
            foreach ($tableRows as $table) {
                $name = (string) ($table['name'] ?? '');

                $foreignKeys = $this->readForeignKeys($name);
                $fkByColumn = [];
                foreach ($foreignKeys as $fk) {
                    $from = (string) ($fk['from'] ?? '');
                    if ($from === '') {
                        continue;
                    }
                    $fkByColumn[$from] = [
                        'table' => (string) ($fk['table'] ?? ''),
                        'column' => (string) ($fk['to'] ?? ''),
                    ];
                }

                $columns = [];
                foreach ($this->readColumns($name) as $column) {
                    $columnName = (string) ($column['name'] ?? '');
                    $type = (string) ($column['type'] ?? '');
                    $pk = (int) ($column['pk'] ?? 0);
                    $notNull = (int) ($column['notnull'] ?? 0);

                    $columns[] = [
                        'name' => $columnName,
                        'dataType' => $type !== '' ? $type : 'BLOB',
                        // A primary key is never nullable; PRAGMA reports
                        // notnull=0 for an INTEGER PRIMARY KEY, so it is
                        // excluded explicitly.
                        'nullable' => $notNull === 0 && $pk === 0,
                        'primaryKey' => $pk > 0,
                        'defaultValue' => $column['dflt_value'] ?? null,
                        'foreignKey' => $fkByColumn[$columnName] ?? null,
                        'ordinal' => (int) ($column['cid'] ?? 0),
                    ];
                }

                $tables[] = [
                    'name' => $name,
                    'columns' => $columns,
                    // A COUNT(*) per table would be one extra REST round-trip per table.
                    'rowsApprox' => null,
                ];
            }

            return ['engine' => 'd1', 'tables' => $tables];
        } catch (Throwable $e) {
            throw $this->wrapIntrospection($e);
        }
    }

    /**
     * Reads one of SQLite's catalog PRAGMAs, preferring the documented PRAGMA
     * statement and falling back to the table-valued function. The working form
     * is remembered for the rest of the connection.
     *
     * @return list<array<string, mixed>>
     */
    private function readColumns(string $table): array
    {
        return $this->readPragma(Queries::tableInfo($table), Queries::TABLE_INFO_FALLBACK, $table);
    }

    /** @return list<array<string, mixed>> */
    private function readForeignKeys(string $table): array
    {
        return $this->readPragma(
            Queries::foreignKeyList($table),
            Queries::FOREIGN_KEY_LIST_FALLBACK,
            $table,
        );
    }

    /** @return list<array<string, mixed>> */
    private function readPragma(string $pragmaSql, string $fallbackSql, string $table): array
    {
        if ($this->pragmaSupported !== false) {
            try {
                $rows = $this->selectRows($pragmaSql);
                $this->pragmaSupported = true;

                return $rows;
            } catch (RuntimeException $e) {
                if ($this->pragmaSupported === true || !$this->isUnsupportedPragma($e)) {
                    throw $e;
                }
                $this->pragmaSupported = false;
            }
        }

        return $this->selectRows($fallbackSql, [$table]);
    }

    /** Runs a statement and returns its rows. */
    private function selectRows(string $sql, array $params = []): array
    {
        $body = $this->request($sql, $params);

        $result = $body['result'] ?? null;
        if (!is_array($result) || !isset($result[0]) || !is_array($result[0])) {
            return [];
        }

        $rows = $result[0]['results'] ?? null;
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $this->assoc($row);
            }
        }

        return $out;
    }

    /**
     * Runs one statement through the REST API and returns the parsed envelope.
     * The endpoint accepts a single `{ sql, params }` query object.
     *
     * @return array<string, mixed>
     */
    private function request(string $sql, array $params): array
    {
        $url = sprintf(
            '%s/accounts/%s/d1/database/%s/query',
            $this->config->baseUrlTrimmed(),
            rawurlencode($this->config->accountId),
            rawurlencode($this->config->databaseId),
        );

        $payload = json_encode(['sql' => $sql, 'params' => array_values($params)]);
        if ($payload === false) {
            throw new RuntimeException('Failed to encode D1 request payload');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }

        curl_setopt_array($ch, $this->curlOptions() + [CURLOPT_POSTFIELDS => $payload]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException($error !== '' ? $error : 'curl request failed');
        }

        $text = (string) $response;
        $decoded = json_decode($text, true);

        if (!is_array($decoded)) {
            if ($status >= 400) {
                throw new RuntimeException(
                    "request to {$url} failed with HTTP {$status}: " . $this->truncate($text, 500),
                );
            }
            throw new RuntimeException('Unexpected D1 response');
        }

        $errors = $decoded['errors'] ?? null;
        $success = $decoded['success'] ?? null;

        if ($status >= 400 || $success === false || (is_array($errors) && $errors !== [])) {
            throw new RuntimeException('D1 query failed: ' . $this->describeErrors($decoded));
        }

        return $this->assoc($decoded);
    }

    /**
     * @param array<mixed, mixed> $row
     * @return array<string, mixed>
     */
    private function assoc(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }

    /**
     * @return array<int, mixed>
     */
    private function curlOptions(): array
    {
        return [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->config->apiToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => (int) ceil($this->config->timeout),
            CURLOPT_CONNECTTIMEOUT => (int) ceil($this->config->timeout),
        ];
    }

    /**
     * True when the failure looks like the REST endpoint refusing a PRAGMA
     * statement rather than a genuine database error. Only syntax/authorisation
     * failures that mention `PRAGMA` qualify; a real error such as
     * `no such table` must propagate.
     */
    private function isUnsupportedPragma(Throwable $error): bool
    {
        $message = strtolower($error->getMessage());
        if (!str_contains($message, 'pragma')) {
            return false;
        }

        foreach (['syntax', 'near ', 'not authorized', 'not supported', 'unsupported'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function describeErrors(array $body): string
    {
        $errors = $body['errors'] ?? null;
        if (!is_array($errors) || $errors === []) {
            return 'no error details returned by the Cloudflare API';
        }

        $parts = [];
        foreach ($errors as $error) {
            if (!is_array($error)) {
                continue;
            }

            $code = isset($error['code']) && is_numeric($error['code'])
                ? '[' . (int) $error['code'] . '] '
                : '';
            $message = (string) ($error['message'] ?? 'unknown error');
            $pointer = '';
            if (isset($error['source']) && is_array($error['source']) && isset($error['source']['pointer'])) {
                $pointer = ' (at ' . (string) $error['source']['pointer'] . ')';
            }

            $parts[] = $code . $message . $pointer;
        }

        return $parts === [] ? 'no error details returned by the Cloudflare API' : implode('; ', $parts);
    }

    private function truncate(string $text, int $max): string
    {
        return strlen($text) > $max ? substr($text, 0, $max) . '…' : $text;
    }
}
