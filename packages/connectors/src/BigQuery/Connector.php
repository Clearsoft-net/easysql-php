<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\BigQuery;

use Clearsoft\EasySQL\Connectors\BaseConnector;
use RuntimeException;
use Throwable;

/**
 * BigQuery connector — local schema introspection and SELECT execution.
 *
 * Uses the optional official `google/cloud-bigquery` client. The dependency is
 * not required by this package (Composer has no optional-per-engine require),
 * so it is guarded with `class_exists`; a missing SDK surfaces as an actionable
 * {@see RuntimeException} at connect time.
 *
 * Introspection uses the client's dataset/table APIs rather than SQL: datasets
 * list their tables and each table's metadata supplies the schema fields and,
 * when present, `numRows`. BigQuery does not model primary or foreign keys, so
 * every column is reported with `primaryKey: false` and `foreignKey: null`.
 */
final class Connector extends BaseConnector
{
    /**
     * The SDK client class, kept as a property so the guarded instantiation is
     * resolved at runtime rather than statically.
     */
    private const CLIENT_CLASS = 'Google\\Cloud\\BigQuery\\BigQueryClient';

    public function __construct(
        private readonly ConnectionConfig $config,
        private readonly string $clientClass = self::CLIENT_CLASS,
    ) {
        // A JSON credentials blob is a secret; a filesystem path is not.
        $credentials = $config->credentialsJson;
        parent::__construct(
            $credentials !== '' && !is_file($credentials) ? [$credentials] : [],
        );
    }

    protected function engine(): string
    {
        return 'BigQuery';
    }

    /**
     * Creates the SDK client, failing with an actionable message when the
     * optional package is not installed.
     */
    protected function openHandle(): object
    {
        if (!class_exists($this->clientClass)) {
            throw new RuntimeException(
                'BigQuery requires the "google/cloud-bigquery" package. '
                . 'Install it with: composer require google/cloud-bigquery',
            );
        }

        $options = ['projectId' => $this->config->projectId];

        if ($this->config->location !== '') {
            $options['location'] = $this->config->location;
        }

        $credentials = $this->config->credentialsJson;
        if ($credentials !== '') {
            if (is_file($credentials)) {
                $options['keyFilePath'] = $credentials;
            } else {
                $decoded = json_decode($credentials, true);
                if (!is_array($decoded)) {
                    throw new RuntimeException(
                        'BigQuery credentialsJson is neither a readable file nor valid JSON.',
                    );
                }
                $options['keyFile'] = $decoded;
            }
        }

        $class = $this->clientClass;

        return new $class($options);
    }

    protected function closeHandle(mixed $handle): void
    {
        // The client holds no explicit connection to release.
    }

    protected function runQuery(mixed $handle, string $sql, array $params): array
    {
        $client = $this->requireClient($handle);

        $options = [];
        if ($this->config->location !== '') {
            $options['location'] = $this->config->location;
        }

        $queryConfig = $client->query($sql, $options);
        $results = $client->runQuery($queryConfig);

        if (!is_iterable($results)) {
            throw new RuntimeException('Unexpected BigQuery query result');
        }

        $rows = [];
        foreach ($results as $row) {
            if (is_array($row)) {
                $rows[] = $this->assoc($row);
            }
        }

        return [
            'columns' => $rows === [] ? [] : array_keys($rows[0]),
            'rows' => $rows,
        ];
    }

    /**
     * Introspect the configured dataset, or every dataset in the project.
     *
     * Returns the raw introspection shape consumed by the schema-generation
     * package: ['engine' => 'bigquery', 'tables' => [['name', 'columns' => [...], 'rowsApprox']]].
     *
     * @return array{engine: string, tables: list<array<string, mixed>>}
     */
    public function introspect(): array
    {
        $client = $this->requireClient($this->requireHandle());

        try {
            $datasetIds = $this->config->datasetId !== ''
                ? [$this->config->datasetId]
                : $this->listDatasetIds($client);

            $tables = [];
            foreach ($datasetIds as $datasetId) {
                $options = [];
                if ($this->config->location !== '') {
                    $options['location'] = $this->config->location;
                }

                $dataset = $client->dataset($datasetId, $options);
                if (!is_object($dataset)) {
                    continue;
                }

                foreach ($this->objects($dataset->tables()) as $table) {
                    $info = $table->info();
                    if (!is_array($info) || !$this->isDataTable($info)) {
                        continue;
                    }

                    $tableId = (string) $table->id();
                    $tables[] = [
                        // A dataset is not part of the raw table shape, so the
                        // dataset id is prefixed when every dataset is listed.
                        'name' => $this->config->datasetId !== ''
                            ? $tableId
                            : $datasetId . '.' . $tableId,
                        'columns' => $this->toRawColumns($info),
                        'rowsApprox' => $this->toRowsApprox($info['numRows'] ?? null),
                    ];
                }
            }

            usort(
                $tables,
                static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']),
            );

            return ['engine' => 'bigquery', 'tables' => $tables];
        } catch (Throwable $e) {
            throw $this->wrapIntrospection($e);
        }
    }

    /**
     * @return list<string>
     */
    private function listDatasetIds(object $client): array
    {
        $ids = [];
        foreach ($this->objects($client->datasets(['all' => true])) as $dataset) {
            $id = $dataset->id();
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Narrows an iterable of SDK objects to a list of objects.
     *
     * @return list<object>
     */
    private function objects(mixed $value): array
    {
        if (!is_iterable($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_object($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /** Skips views and other metadata-only entries. */
    private function isDataTable(array $info): bool
    {
        $type = $info['type'] ?? null;
        if (!is_string($type) || $type === '') {
            return true;
        }

        return in_array($type, ['TABLE', 'EXTERNAL', 'MATERIALIZED_VIEW'], true);
    }

    /**
     * Maps a table's metadata schema to raw columns. `ordinal` is the field's
     * position; nested `RECORD` fields keep their type string and are never
     * flattened.
     *
     * @param array<string, mixed> $info
     * @return list<array<string, mixed>>
     */
    private function toRawColumns(array $info): array
    {
        $schema = $info['schema'] ?? null;
        $fields = is_array($schema) ? ($schema['fields'] ?? null) : null;
        if (!is_array($fields)) {
            return [];
        }

        $columns = [];
        $index = 0;
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $type = isset($field['type']) ? (string) $field['type'] : 'UNKNOWN';
            $mode = isset($field['mode']) ? (string) $field['mode'] : null;

            $columns[] = [
                'name' => (string) ($field['name'] ?? ''),
                // `REPEATED` is a BigQuery mode, surfaced as a type prefix.
                'dataType' => $mode === 'REPEATED' ? 'REPEATED ' . $type : $type,
                // BigQuery columns are nullable unless mode is REQUIRED.
                'nullable' => $mode !== 'REQUIRED',
                'defaultValue' => $field['defaultValueExpression'] ?? null,
                // BigQuery does not model primary or foreign keys.
                'primaryKey' => false,
                'foreignKey' => null,
                'ordinal' => $index,
            ];
            $index++;
        }

        return $columns;
    }

    /** `numRows` arrives as a string from the REST API; ignore unparsable values. */
    private function toRowsApprox(mixed $numRows): ?int
    {
        if ($numRows === null || !is_numeric($numRows)) {
            return null;
        }

        return (int) $numRows;
    }

    private function requireClient(mixed $handle): object
    {
        if (!is_object($handle)) {
            throw new RuntimeException('BigQuery: not connected.');
        }

        return $handle;
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
