<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Tests\LibSql;

use Clearsoft\EasySQL\Common\SqlValidator;
use Clearsoft\EasySQL\Connectors\ConnectorException;
use Clearsoft\EasySQL\Connectors\LibSql\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\LibSql\Connector;
use PDO;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixturePath = sys_get_temp_dir() . '/easysql_libsql_test_' . getmypid() . '.db';
        @unlink($this->fixturePath);

        $pdo = new PDO('sqlite:' . $this->fixturePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE customers (id INTEGER PRIMARY KEY, email TEXT NOT NULL, name TEXT)');
        $pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, customer_id INTEGER NOT NULL REFERENCES customers(id), total REAL, placed_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec("INSERT INTO customers (id, email, name) VALUES (1, 'a@example.com', 'Alice'), (2, 'b@example.com', 'Bob')");
        $pdo->exec("INSERT INTO orders (id, customer_id, total) VALUES (1, 1, 9.99), (2, 2, 19.99)");
        $pdo = null;
    }

    protected function tearDown(): void
    {
        @unlink($this->fixturePath);
    }

    // ── Config ────────────────────────────────────────────────

    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame(':memory:', $config->url);
        $this->assertSame('', $config->authToken);
        $this->assertSame(10.0, $config->timeout);
        $this->assertTrue($config->readOnly);
    }

    public function testLocalFileDefaultsToReadOnly(): void
    {
        $config = new ConnectionConfig(url: $this->fixturePath);

        $this->assertTrue($config->isFile());
        $this->assertTrue($config->readOnly);
        $this->assertSame('sqlite:' . $this->fixturePath, $config->toDsn());
    }

    public function testRemoteUrlIsNotReadOnly(): void
    {
        $config = new ConnectionConfig(url: 'libsql://shop.turso.io');

        $this->assertFalse($config->isFile());
        $this->assertFalse($config->readOnly);
    }

    public function testFromUrlAcceptsBarePathAndSchemes(): void
    {
        $this->assertTrue(ConnectionConfig::fromUrl($this->fixturePath)->isFile());
        $this->assertSame('/tmp/shop.db', ConnectionConfig::fromUrl('file:/tmp/shop.db')->filePath());

        $remote = ConnectionConfig::fromUrl('turso://shop.turso.io');
        $this->assertSame('turso://shop.turso.io', $remote->url);
        $this->assertFalse($remote->isFile());
    }

    public function testFromUrlRejectsAnotherEngine(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConnectionConfig::fromUrl('mysql://root@127.0.0.1:3306/shop');
    }

    // ── Validator ─────────────────────────────────────────────

    public function testValidatorAcceptsSelect(): void
    {
        $this->assertTrue(SqlValidator::validateSelectOnly('SELECT * FROM customers LIMIT 10')['ok']);
    }

    public function testValidatorRejectsMutations(): void
    {
        foreach (['DELETE FROM customers', 'DROP TABLE customers', 'INSERT INTO customers VALUES (1)'] as $sql) {
            $this->assertFalse(SqlValidator::validateSelectOnly($sql)['ok'], $sql);
        }
    }

    // ── Introspection (local file) ────────────────────────────

    public function testIntrospectFixtureDatabase(): void
    {
        $connector = new Connector(new ConnectionConfig(url: $this->fixturePath));
        $connector->connect();

        try {
            $raw = $connector->introspect();
        } finally {
            $connector->close();
        }

        $this->assertSame('libsql', $raw['engine']);
        $this->assertSame(['customers', 'orders'], array_column($raw['tables'], 'name'));

        $customers = $raw['tables'][0];
        $this->assertSame(2, $customers['rowsApprox']);
        $this->assertSame(['id', 'email', 'name'], array_column($customers['columns'], 'name'));
        $this->assertTrue($customers['columns'][0]['primaryKey']);
        $this->assertFalse($customers['columns'][1]['nullable']);

        $orders = $raw['tables'][1];
        $this->assertSame(
            ['table' => 'customers', 'column' => 'id'],
            $orders['columns'][1]['foreignKey'],
        );
    }

    // ── Execution (local file) ────────────────────────────────

    public function testExecuteReturnsTypedRows(): void
    {
        $connector = new Connector(new ConnectionConfig(url: $this->fixturePath));
        $connector->connect();

        try {
            $result = $connector->execute('SELECT id, email FROM customers ORDER BY id');
        } finally {
            $connector->close();
        }

        $this->assertSame(['id', 'email'], $result['columns']);
        $this->assertSame(2, $result['row_count']);
        $this->assertSame('a@example.com', $result['rows'][0]['email']);
    }

    public function testReadOnlyRejectsWrites(): void
    {
        $connector = new Connector(new ConnectionConfig(url: $this->fixturePath));
        $connector->connect();

        try {
            $connector->execute("INSERT INTO customers (email) VALUES ('x@example.com')");
            $this->fail('Expected ConnectorException');
        } catch (ConnectorException $e) {
            // Either the safety validator or SQLite's read-only enforcement rejects it
            $this->assertNotEmpty($e->getMessage());
        } finally {
            $connector->close();
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

    // ── Integration (requires Turso/libSQL credentials) ───────

    public function testIntegrationAgainstLibSql(): void
    {
        if (getenv('EASYSQL_TEST_LIBSQL') !== '1') {
            $this->markTestSkipped('Set EASYSQL_TEST_LIBSQL=1 with libSQL credentials to run integration tests.');
        }

        $connector = new Connector(new ConnectionConfig(
            url: (string) getenv('EASYSQL_TEST_LIBSQL_URL'),
            authToken: (string) getenv('EASYSQL_TEST_LIBSQL_TOKEN'),
        ));
        $connector->connect();

        try {
            $raw = $connector->introspect();
            $this->assertSame('libsql', $raw['engine']);
            $this->assertArrayHasKey('tables', $raw);

            $result = $connector->execute('SELECT 1 AS one');
            $this->assertSame(['one'], $result['columns']);
            $this->assertSame(1, $result['row_count']);
        } finally {
            $connector->close();
        }
    }
}
