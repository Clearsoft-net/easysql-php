<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Postgres;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use Clearsoft\EasySQL\Connectors\BaseConnector;
use RuntimeException;
use Throwable;

/**
 * PostgreSQL connector — local schema introspection and SELECT execution.
 *
 * The customer database is introspected locally and only the schema reaches
 * the API, which never stores credentials or executes SQL.
 *
 * The same connector serves every PostgreSQL-compatible server. The `provider`
 * selects which catalog queries are safe (Redshift is PostgreSQL 8.0 based) and
 * the `transport` selects how the server is reached: the wire protocol over TCP
 * (PDO) or Neon's HTTP SQL endpoint.
 *
 * No global state: several connections can be open at the same time.
 */
final class Connector extends BaseConnector
{
    public function __construct(private readonly ConnectionConfig $config)
    {
        parent::__construct(array_filter(
            [$config->password, $config->connectionString ?? ''],
            static fn (string $secret): bool => $secret !== '',
        ));
    }

    protected function engine(): string
    {
        return 'PostgreSQL';
    }

    protected function openHandle(): PgSession
    {
        $session = $this->createSession();
        $session->open();

        return $session;
    }

    protected function closeHandle(mixed $handle): void
    {
        if ($handle instanceof PgSession) {
            $handle->close();
        }
    }

    protected function runQuery(mixed $handle, string $sql, array $params): array
    {
        if (!$handle instanceof PgSession) {
            throw new RuntimeException('PostgreSQL session is not open.');
        }

        return $handle->query($sql, $params);
    }

    /**
     * Introspect the configured schemas.
     *
     * Returns the raw introspection shape consumed by the schema-generation
     * package: ['engine' => 'postgresql', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     * Types are reported verbatim from `format_type()`; the schema-generation
     * package maps them into the canonical vocabulary.
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $session = $this->requireHandle();
        if (!$session instanceof PgSession) {
            throw new RuntimeException('PostgreSQL session is not open.');
        }

        try {
            $tablesSql = Queries::tables($this->config->provider);

            $tables = [];
            foreach ($this->config->schemas as $schema) {
                $tableRows = $session->query($tablesSql, ['schema' => $schema]);

                foreach ($tableRows['rows'] as $table) {
                    $columnRows = $session->query(Queries::COLUMNS, [
                        'schema' => $schema,
                        'table' => $table['table_name'],
                    ]);

                    $columns = [];
                    foreach ($columnRows['rows'] as $column) {
                        $columns[] = [
                            'name' => $column['column_name'],
                            'dataType' => $column['data_type'],
                            'nullable' => $this->toBool($column['is_nullable']),
                            'primaryKey' => $this->toBool($column['is_primary']),
                            'defaultValue' => $column['column_default'],
                            'foreignKey' => $column['foreign_table'] !== null
                                && $column['foreign_column'] !== null
                                ? [
                                    'table' => $column['foreign_table'],
                                    'column' => $column['foreign_column'],
                                ]
                                : null,
                            'ordinal' => (int) $column['ordinal'],
                        ];
                    }

                    $tables[] = [
                        'name' => $table['table_name'],
                        'columns' => $columns,
                        'rowsApprox' => $table['rows_approx'] !== null
                            ? (int) $table['rows_approx']
                            : null,
                    ];
                }
            }

            return ['engine' => 'postgresql', 'tables' => $tables];
        } catch (Throwable $e) {
            throw $this->wrapIntrospection($e);
        }
    }

    private function createSession(): PgSession
    {
        return match ($this->config->transport) {
            ConnectionConfig::TRANSPORT_HTTP => new NeonHttpSession(
                $this->requireConnectionString(),
                $this->endpoint(),
                $this->config->timeout,
            ),
            ConnectionConfig::TRANSPORT_WEBSOCKET => throw new RuntimeException(
                "PostgreSQL transport 'websocket' is not supported by the PHP SDK "
                . "(PHP has no PostgreSQL-over-WebSocket client); use 'tcp' or 'http'.",
            ),
            default => new TcpSession($this->config),
        };
    }

    private function requireConnectionString(): string
    {
        $connectionString = $this->config->connectionString;
        if ($connectionString === null || $connectionString === '') {
            throw new RuntimeException(
                "PostgreSQL transport '{$this->config->transport}' requires a connection string.",
            );
        }

        return $connectionString;
    }

    /**
     * The Neon HTTP endpoint: `https://api.<host>/sql`, derived from the
     * connection string's host (the serverless driver replaces the first host
     * label with `api.`). An explicit `endpoint` overrides it.
     */
    private function endpoint(): string
    {
        if ($this->config->endpoint !== null && $this->config->endpoint !== '') {
            return $this->config->endpoint;
        }

        $parsed = ConnectionUrl::parse($this->requireConnectionString());
        $apiHost = (string) preg_replace('/^[^.]+/', 'api', $parsed['host']);

        return sprintf('https://%s/sql', $apiHost);
    }

    /**
     * PDO pgsql and Neon's raw-text output return booleans as PHP bool or as
     * 't'/'f' strings depending on the build; normalize both into a real bool.
     */
    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, ['t', 'true', '1', 1, 'y', 'yes'], true);
    }
}
