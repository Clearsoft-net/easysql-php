<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\LibSql;

use Clearsoft\EasySQL\Connectors\BaseConnector;
use PDO;
use RuntimeException;
use Throwable;

/**
 * libSQL/Turso connector — local schema introspection and SELECT execution.
 *
 * libSQL speaks SQLite, so introspection reads the SQLite catalog. Two modes
 * are supported:
 *
 * - **Local** (`file:...` or a bare path): opened with PDO over SQLite exactly
 *   like the {@see \Clearsoft\EasySQL\Connectors\SQLite\Connector}. A local
 *   file defaults to read-only, enforced with the SQLite open flag or
 *   `PRAGMA query_only`.
 * - **Remote** (`libsql://` / `turso://` / `https://`): reached over HTTP with
 *   an `Authorization: Bearer` token. The libSQL/Hrana protocol below is a
 *   best-effort SQL-over-HTTP encoding, not a full Hrana client; it is
 *   implemented defensively and its response shape is unverified.
 */
final class Connector extends BaseConnector
{
    public function __construct(private readonly ConnectionConfig $config)
    {
        parent::__construct(array_filter(
            [$config->authToken],
            static fn (string $secret): bool => $secret !== '',
        ));
    }

    protected function engine(): string
    {
        return 'libSQL';
    }

    protected function openHandle(): mixed
    {
        if ($this->config->isFile()) {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => (int) ceil($this->config->timeout),
            ];

            $enforceQueryOnly = false;

            if ($this->config->readOnly) {
                if (defined('PDO::SQLITE_ATTR_OPEN_FLAGS') && defined('PDO::SQLITE_OPEN_READONLY')) {
                    $options[PDO::SQLITE_ATTR_OPEN_FLAGS] = PDO::SQLITE_OPEN_READONLY;
                } else {
                    $enforceQueryOnly = true;
                }
            }

            $pdo = new PDO($this->config->toDsn(), null, null, $options);

            if ($enforceQueryOnly) {
                $pdo->exec('PRAGMA query_only = 1');
            }

            return $pdo;
        }

        // Remote: a cheap statement proves the endpoint and token work. The
        // HTTP protocol is stateless, so the handle is just a marker.
        $this->httpQuery('SELECT 1');

        return true;
    }

    protected function closeHandle(mixed $handle): void
    {
        // PDO releases its connection when the reference is dropped; the
        // stateless HTTP mode holds nothing.
    }

    protected function runQuery(mixed $handle, string $sql, array $params): array
    {
        return $this->fetchRows($handle, $sql, $params);
    }

    /**
     * Introspect the database schema.
     *
     * Returns the raw introspection shape consumed by the schema-generation
     * package: ['engine' => 'libsql', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $handle = $this->requireHandle();

        try {
            $tableSet = $this->fetchRows($handle, Queries::TABLES);

            $tables = [];
            foreach ($tableSet['rows'] as $table) {
                $name = (string) ($table['name'] ?? '');

                $fkByColumn = [];
                foreach ($this->fetchRows($handle, Queries::foreignKeyList($name))['rows'] as $fk) {
                    $from = (string) ($fk['from'] ?? '');
                    $to = $fk['to'] ?? null;
                    if ($from === '' || $to === null || $to === '') {
                        continue;
                    }
                    $fkByColumn[$from] = [
                        'table' => (string) ($fk['table'] ?? ''),
                        'column' => (string) $to,
                    ];
                }

                $columns = [];
                foreach ($this->fetchRows($handle, Queries::tableInfo($name))['rows'] as $column) {
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
                    'rowsApprox' => $this->countRows($handle, $name),
                ];
            }

            return ['engine' => 'libsql', 'tables' => $tables];
        } catch (Throwable $e) {
            throw $this->wrapIntrospection($e);
        }
    }

    /**
     * Runs `sql` against the open handle, dispatching between PDO and HTTP.
     *
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function fetchRows(mixed $handle, string $sql, array $params = []): array
    {
        if (!$handle instanceof PDO) {
            return $this->httpQuery($sql, $params);
        }

        $stmt = $handle->prepare($sql);
        $stmt->execute($params);
        $fetched = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rows = [];
        foreach ($fetched as $row) {
            if (is_array($row)) {
                $rows[] = $this->assoc($row);
            }
        }

        return [
            'columns' => $rows === [] ? [] : array_keys($rows[0]),
            'rows' => $rows,
        ];
    }

    /** Best-effort exact row count; returns `null` when the table cannot be read. */
    private function countRows(mixed $handle, string $table): ?int
    {
        try {
            $first = $this->fetchRows($handle, Queries::rowCount($table))['rows'][0] ?? null;
            if (!is_array($first)) {
                return null;
            }

            $value = $first['c'] ?? null;

            return is_numeric($value) ? (int) $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Runs one statement over HTTP.
     *
     * This is a best-effort SQL-over-HTTP encoding of the libSQL/Hrana execute
     * request; the response shape below (`results[0].response.result` carrying
     * `cols`/`rows`) is UNVERIFIED against a live server and is parsed
     * defensively, accepting both `{value: ...}`-wrapped and bare cells.
     *
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function httpQuery(string $sql, array $params = []): array
    {
        $payload = json_encode([
            'requests' => [[
                'type' => 'execute',
                'stmt' => ['sql' => $sql, 'args' => array_values($params)],
            ]],
        ]);
        if ($payload === false) {
            throw new RuntimeException('Failed to encode libSQL request payload');
        }

        $ch = curl_init($this->httpEndpoint());
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
        if ($status >= 400) {
            throw new RuntimeException(trim($text) !== '' ? trim($text) : "HTTP {$status}");
        }

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Unexpected libSQL response');
        }

        return $this->parseHttpResult($decoded);
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function parseHttpResult(array $decoded): array
    {
        $results = $decoded['results'] ?? null;
        $entry = is_array($results) && isset($results[0]) && is_array($results[0]) ? $results[0] : null;

        $response = null;
        if ($entry !== null) {
            $response = isset($entry['response']) && is_array($entry['response']) ? $entry['response'] : $entry;
        }
        $result = is_array($response) && isset($response['result']) && is_array($response['result'])
            ? $response['result']
            : null;

        $columns = [];
        if (is_array($result) && isset($result['cols']) && is_array($result['cols'])) {
            foreach ($result['cols'] as $col) {
                if (is_array($col)) {
                    $columns[] = (string) ($col['name'] ?? '');
                }
            }
        }

        $rows = [];
        if (is_array($result) && isset($result['rows']) && is_array($result['rows'])) {
            foreach ($result['rows'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $rows[] = $this->normalizeHttpRow($row, $columns);
            }
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * @param array<mixed, mixed> $row
     * @param list<string> $columns
     * @return array<string, mixed>
     */
    private function normalizeHttpRow(array $row, array $columns): array
    {
        $out = [];
        $positional = array_is_list($row);
        $index = 0;

        foreach ($row as $key => $cell) {
            $name = $positional ? ($columns[$index] ?? (string) $index) : (string) $key;
            $index++;
            $out[$name] = is_array($cell) && array_key_exists('value', $cell) ? $cell['value'] : $cell;
        }

        return $out;
    }

    /** Maps a libSQL URL scheme onto its HTTP endpoint. */
    private function httpEndpoint(): string
    {
        $url = $this->config->url;

        foreach (['libsql://' => 'https://', 'turso://' => 'https://', 'wss://' => 'https://', 'ws://' => 'https://'] as $from => $to) {
            if (stripos($url, $from) === 0) {
                return $to . substr($url, strlen($from));
            }
        }

        return $url;
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
                'Authorization: Bearer ' . $this->config->authToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => (int) ceil($this->config->timeout),
            CURLOPT_CONNECTTIMEOUT => (int) ceil($this->config->timeout),
        ];
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
}
