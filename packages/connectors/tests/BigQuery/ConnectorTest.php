<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Tests\BigQuery;

use Clearsoft\EasySQL\Common\SqlValidator;
use Clearsoft\EasySQL\Connectors\BigQuery\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\BigQuery\Connector;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    // ── Config ────────────────────────────────────────────────

    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame('', $config->projectId);
        $this->assertSame('', $config->datasetId);
        $this->assertSame('', $config->credentialsJson);
        $this->assertSame('', $config->location);
        $this->assertSame(10.0, $config->timeout);
    }

    public function testFromUrlBuildsConfig(): void
    {
        $config = ConnectionConfig::fromUrl('bigquery://my-project/analytics');

        $this->assertSame('my-project', $config->projectId);
        $this->assertSame('analytics', $config->datasetId);
    }

    public function testFromUrlRejectsAnotherEngine(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConnectionConfig::fromUrl('mysql://root@127.0.0.1:3306/shop');
    }

    // ── Validator ─────────────────────────────────────────────

    public function testValidatorAcceptsSelect(): void
    {
        $this->assertTrue(SqlValidator::validateSelectOnly('SELECT * FROM `p.d.t` LIMIT 10')['ok']);
    }

    public function testValidatorRejectsMutations(): void
    {
        foreach (['DELETE FROM `p.d.t`', 'DROP TABLE `p.d.t`'] as $sql) {
            $this->assertFalse(SqlValidator::validateSelectOnly($sql)['ok'], $sql);
        }
    }

    // ── Missing optional SDK ──────────────────────────────────

    public function testConnectWithoutSdkThrowsActionableError(): void
    {
        if (class_exists('Google\\Cloud\\BigQuery\\BigQueryClient')) {
            $this->markTestSkipped('google/cloud-bigquery is installed; the missing-SDK path is not exercised.');
        }

        $connector = new Connector(new ConnectionConfig(projectId: 'my-project'));

        try {
            $connector->connect();
            $this->fail('Expected a RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('google/cloud-bigquery', $e->getMessage());
        }
    }

    public function testExecuteWithoutConnectThrows(): void
    {
        $connector = new Connector(new ConnectionConfig());

        $this->expectException(\RuntimeException::class);

        $connector->execute('SELECT 1');
    }

    public function testIntrospectWithoutConnectThrows(): void
    {
        $connector = new Connector(new ConnectionConfig());

        $this->expectException(\RuntimeException::class);

        $connector->introspect();
    }

    // ── Integration (requires Google Cloud credentials) ───────

    public function testIntegrationAgainstBigQuery(): void
    {
        if (getenv('EASYSQL_TEST_BIGQUERY_PROJECT') === false) {
            $this->markTestSkipped('Set EASYSQL_TEST_BIGQUERY_PROJECT with credentials to run integration tests.');
        }

        $connector = new Connector(new ConnectionConfig(
            projectId: (string) getenv('EASYSQL_TEST_BIGQUERY_PROJECT'),
            datasetId: (string) getenv('EASYSQL_TEST_BIGQUERY_DATASET'),
            credentialsJson: (string) getenv('EASYSQL_TEST_BIGQUERY_CREDENTIALS'),
            location: (string) getenv('EASYSQL_TEST_BIGQUERY_LOCATION'),
        ));
        $connector->connect();

        try {
            $raw = $connector->introspect();
            $this->assertSame('bigquery', $raw['engine']);
            $this->assertArrayHasKey('tables', $raw);

            $result = $connector->execute('SELECT 1 AS one');
            $this->assertSame(['one'], $result['columns']);
            $this->assertSame(1, $result['row_count']);
        } finally {
            $connector->close();
        }
    }
}
