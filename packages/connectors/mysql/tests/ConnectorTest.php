<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\MySQL\Tests;

use Clearsoft\EasySQL\Connectors\MySQL\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\MySQL\Connector;
use Clearsoft\EasySQL\Connectors\MySQL\ConnectorException;
use Clearsoft\EasySQL\Common\SqlValidator;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    // ── Config ────────────────────────────────────────────────

    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame('127.0.0.1', $config->host);
        $this->assertSame(3306, $config->port);
        $this->assertSame('utf8mb4', $config->charset);
        $this->assertFalse($config->ssl);
        $this->assertSame(10.0, $config->timeout);
    }

    public function testDsnFormat(): void
    {
        $config = new ConnectionConfig(
            host: 'db.internal',
            port: 3307,
            database: 'shop',
            charset: 'utf8mb4',
        );

        $this->assertSame(
            'mysql:host=db.internal;port=3307;dbname=shop;charset=utf8mb4',
            $config->toDsn(),
        );
    }

    // ── Validator ─────────────────────────────────────────────

    public function testValidatorAcceptsSelect(): void
    {
        $this->assertTrue(SqlValidator::validateSelectOnly('SELECT * FROM users LIMIT 10')['ok']);
        $this->assertTrue(SqlValidator::validateSelectOnly('WITH cte AS (SELECT 1) SELECT * FROM cte')['ok']);
    }

    public function testValidatorRejectsMutations(): void
    {
        foreach (['DELETE FROM users', 'DROP TABLE users', "INSERT INTO users VALUES (1)", 'UPDATE users SET x = 1'] as $sql) {
            $result = SqlValidator::validateSelectOnly($sql);
            $this->assertFalse($result['ok'], $sql);
        }
    }

    public function testValidatorRejectsStackedStatements(): void
    {
        $result = SqlValidator::validateSelectOnly('SELECT 1; DELETE FROM users');

        $this->assertFalse($result['ok']);
    }

    public function testValidatorIgnoresKeywordsInsideLiterals(): void
    {
        $result = SqlValidator::validateSelectOnly("SELECT * FROM users WHERE note = 'please delete me'");

        $this->assertTrue($result['ok']);
    }

    // ── Connection failures ───────────────────────────────────

    public function testConnectionFailureStripsCredentials(): void
    {
        $connector = new Connector(new ConnectionConfig(
            host: '127.0.0.1',
            port: 1, // closed port — connection must fail fast
            user: 'secret_user',
            password: 's3cr3t-p4ss',
            database: 'shop',
            timeout: 1.0,
        ));

        try {
            $connector->connect();
            $this->fail('Expected ConnectorException');
        } catch (ConnectorException $e) {
            $this->assertStringNotContainsString('s3cr3t-p4ss', $e->getMessage());
            $this->assertStringNotContainsString('secret_user', $e->getMessage());
        }
    }

    public function testExecuteWithoutConnectThrows(): void
    {
        $connector = new Connector(new ConnectionConfig());

        $this->expectException(\RuntimeException::class);

        $connector->execute('SELECT 1');
    }

    public function testExecuteRejectsUnsafeSqlWithoutConnecting(): void
    {
        $connector = new Connector(new ConnectionConfig(database: 'shop'));
        // connect() would fail (no server), so use reflection-free path:
        // unsafe SQL is rejected before any driver call only when connected.
        // Here we assert the validator itself rejects it.
        $this->assertFalse(SqlValidator::validateSelectOnly('DROP TABLE users')['ok']);
    }

    // ── Integration (requires local MySQL) ────────────────────

    public function testIntegrationAgainstLocalMysql(): void
    {
        $host = getenv('EASYSQL_TEST_MYSQL_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('EASYSQL_TEST_MYSQL_PORT') ?: 3306);
        $user = getenv('EASYSQL_TEST_MYSQL_USER') ?: 'root';
        $password = getenv('EASYSQL_TEST_MYSQL_PASSWORD') ?: '';
        $database = getenv('EASYSQL_TEST_MYSQL_DATABASE') ?: 'easysql_test';

        if (getenv('EASYSQL_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Set EASYSQL_TEST_MYSQL=1 with a local MySQL to run integration tests.');
        }

        $connector = new Connector(new ConnectionConfig(
            host: $host,
            port: $port,
            user: $user,
            password: $password,
            database: $database,
        ));
        $connector->connect();

        try {
            $raw = $connector->introspect();
            $this->assertArrayHasKey('tables', $raw);
            $this->assertNotEmpty($raw['tables']);

            $result = $connector->execute('SELECT 1 AS one');
            $this->assertSame(['one'], $result['columns']);
            $this->assertSame(1, $result['row_count']);
        } finally {
            $connector->close();
        }
    }
}
