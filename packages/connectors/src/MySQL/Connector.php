<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\MySQL;

use Clearsoft\EasySQL\Connectors\BaseConnector;
use PDO;
use Throwable;

/**
 * MySQL/MariaDB connector — local schema introspection and SELECT execution.
 *
 * The customer database is introspected locally and only the schema reaches
 * the API, which never stores credentials or executes SQL.
 *
 * No global state: several connections can be open at the same time.
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
        return 'MySQL';
    }

    protected function openHandle(): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => (int) ceil($this->config->timeout),
        ];

        if ($this->config->ssl) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        return new PDO(
            $this->config->toDsn(),
            $this->config->user,
            $this->config->password,
            $options,
        );
    }

    protected function closeHandle(mixed $handle): void
    {
        // PDO releases its connection when the reference is dropped.
    }

    protected function runQuery(mixed $handle, string $sql, array $params): array
    {
        assert($handle instanceof PDO);

        $stmt = $handle->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'columns' => $rows === [] ? [] : array_keys($rows[0]),
            'rows' => $rows,
        ];
    }

    /**
     * Introspect the database schema.
     *
     * Returns the raw introspection shape consumed by the schema-generation
     * package: ['engine' => 'mysql', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $handle = $this->requireHandle();
        assert($handle instanceof PDO);

        try {
            $tablesStmt = $handle->prepare(Queries::TABLES);
            $tablesStmt->execute(['schema' => $this->config->database]);

            $columnsStmt = $handle->prepare(Queries::COLUMNS);

            $tables = [];
            foreach ($tablesStmt->fetchAll() as $table) {
                $columnsStmt->execute([
                    'schema' => $this->config->database,
                    'table' => $table['table_name'],
                ]);

                $columns = [];
                foreach ($columnsStmt->fetchAll() as $column) {
                    $columns[] = [
                        'name' => $column['column_name'],
                        'dataType' => $column['data_type'],
                        'nullable' => $column['is_nullable'] === 'YES',
                        'primaryKey' => $column['column_key'] === 'PRI',
                        'defaultValue' => $column['column_default'],
                        'foreignKey' => $column['referenced_table_name'] !== null
                            && $column['referenced_column_name'] !== null
                            ? [
                                'table' => $column['referenced_table_name'],
                                'column' => $column['referenced_column_name'],
                            ]
                            : null,
                        'ordinal' => (int) $column['ordinal_position'],
                    ];
                }

                $tables[] = [
                    'name' => $table['table_name'],
                    'columns' => $columns,
                    'rowsApprox' => $table['table_rows'] !== null
                        ? (int) $table['table_rows']
                        : null,
                ];
            }

            return ['engine' => $this->config->engine, 'tables' => $tables];
        } catch (Throwable $e) {
            throw $this->wrapIntrospection($e);
        }
    }
}
