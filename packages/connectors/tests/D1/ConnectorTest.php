<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Tests\D1;

use Clearsoft\EasySQL\Common\SqlValidator;
use Clearsoft\EasySQL\Connectors\ConnectorException;
use Clearsoft\EasySQL\Connectors\D1\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\D1\Connector;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    // ── Config ────────────────────────────────────────────────

    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame('', $config->accountId);
        $this->assertSame('', $config->databaseId);
        $this->assertSame('', $config->apiToken);
        $this->assertSame(ConnectionConfig::DEFAULT_BASE_URL, $config->baseUrl);
        $this->assertSame(10.0, $config->timeout);
    }

    public function testFromUrlBuildsConfig(): void
    {
        $config = ConnectionConfig::fromUrl('d1://acct123/db456?api_token=tok_abc');

        $this->assertSame('acct123', $config->accountId);
        $this->assertSame('db456', $config->databaseId);
        $this->assertSame('tok_abc', $config->apiToken);
    }

    public function testFromUrlRejectsAnotherEngine(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConnectionConfig::fromUrl('mysql://root@127.0.0.1:3306/shop');
    }

    public function testBaseUrlTrimsTrailingSlashes(): void
    {
        $config = new ConnectionConfig(accountId: 'a', databaseId: 'd', apiToken: 't', baseUrl: 'https://proxy.test/v4/');

        $this->assertSame('https://proxy.test/v4', $config->baseUrlTrimmed());
    }

    // ── Validator ─────────────────────────────────────────────

    public function testValidatorAcceptsSelect(): void
    {
        $this->assertTrue(SqlValidator::validateSelectOnly('SELECT * FROM users LIMIT 10')['ok']);
    }

    public function testValidatorRejectsMutations(): void
    {
        foreach (['DELETE FROM users', 'DROP TABLE users', 'INSERT INTO users VALUES (1)'] as $sql) {
            $this->assertFalse(SqlValidator::validateSelectOnly($sql)['ok'], $sql);
        }
    }

    // ── Connection failures ───────────────────────────────────

    public function testConnectionFailureStripsApiToken(): void
    {
        $connector = new Connector(new ConnectionConfig(
            accountId: 'acct',
            databaseId: 'db',
            apiToken: 's3cr3t-t0ken',
            baseUrl: 'http://127.0.0.1:1', // closed port — fails fast
            timeout: 1.0,
        ));

        try {
            $connector->connect();
            $this->fail('Expected ConnectorException');
        } catch (ConnectorException $e) {
            $this->assertStringNotContainsString('s3cr3t-t0ken', $e->getMessage());
            $this->assertStringContainsString('D1', $e->getMessage());
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

    // ── Integration (requires Cloudflare D1 credentials) ──────

    public function testIntegrationAgainstD1(): void
    {
        if (getenv('EASYSQL_TEST_D1') !== '1') {
            $this->markTestSkipped('Set EASYSQL_TEST_D1=1 with D1 credentials to run integration tests.');
        }

        $connector = new Connector(new ConnectionConfig(
            accountId: (string) getenv('EASYSQL_TEST_D1_ACCOUNT_ID'),
            databaseId: (string) getenv('EASYSQL_TEST_D1_DATABASE_ID'),
            apiToken: (string) getenv('EASYSQL_TEST_D1_API_TOKEN'),
        ));
        $connector->connect();

        try {
            $raw = $connector->introspect();
            $this->assertSame('d1', $raw['engine']);
            $this->assertArrayHasKey('tables', $raw);

            $result = $connector->execute('SELECT 1 AS one');
            $this->assertSame(['one'], $result['columns']);
            $this->assertSame(1, $result['row_count']);
        } finally {
            $connector->close();
        }
    }
}
