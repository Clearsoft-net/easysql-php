<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\SQLite\Tests;

use Clearsoft\EasySQL\Connectors\SQLite\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\SQLite\Connector;
use Clearsoft\EasySQL\Connectors\SQLite\ConnectorException;
use Clearsoft\EasySQL\Common\SqlValidator;
use PDO;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixturePath = sys_get_temp_dir() . '/easysql_sqlite_test_' . getmypid() . '.db';
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

        $this->assertSame(':memory:', $config->path);
        $this->assertFalse($config->readOnly);
    }

    public function testDsnFormat(): void
    {
        $config = new ConnectionConfig(path: '/tmp/shop.db');

        $this->assertSame('sqlite:/tmp/shop.db', $config->toDsn());
    }

    // ── Validator ─────────────────────────────────────────────

    public function testValidatorAcceptsSelect(): void
    {
        $this->assertTrue(SqlValidator::validateSelectOnly('SELECT * FROM customers LIMIT 10')['ok']);
    }

    public function testValidatorRejectsMutations(): void
    {
        foreach (['DELETE FROM customers', 'DROP TABLE customers', 'VACUUM', 'INSERT INTO customers VALUES (1)'] as $sql) {
            $this->assertFalse(SqlValidator::validateSelectOnly($sql)['ok'], $sql);
        }
    }

    // ── Introspection ─────────────────────────────────────────

    public function testIntrospectFixtureDatabase(): void
    {
        $connector = new Connector(new ConnectionConfig(path: $this->fixturePath));
        $connector->connect();

        try {
            $raw = $connector->introspect();
        } finally {
            $connector->close();
        }

        $this->assertArrayHasKey('tables', $raw);
        $this->assertSame(['customers', 'orders'], array_column($raw['tables'], 'name'));

        $customers = $raw['tables'][0];
        $this->assertSame(2, $customers['rows_approx']);
        $this->assertSame(['id', 'email', 'name'], array_column($customers['columns'], 'name'));
        $this->assertTrue($customers['columns'][0]['primary_key']);
        $this->assertFalse($customers['columns'][1]['nullable']);

        $orders = $raw['tables'][1];
        $this->assertSame(
            ['table' => 'customers', 'column' => 'id'],
            $orders['columns'][1]['foreign_key'],
        );
    }

    public function testIntrospectEmptyDatabase(): void
    {
        $connector = new Connector(new ConnectionConfig(path: ':memory:'));
        $connector->connect();

        try {
            $raw = $connector->introspect();
        } finally {
            $connector->close();
        }

        $this->assertSame(['tables' => []], $raw);
    }

    public function testIntrospectReadOnly(): void
    {
        $connector = new Connector(new ConnectionConfig(
            path: $this->fixturePath,
            readOnly: true,
        ));
        $connector->connect();

        try {
            $raw = $connector->introspect();
        } finally {
            $connector->close();
        }

        $this->assertSame(2, count($raw['tables']));
    }

    // ── Execution ─────────────────────────────────────────────

    public function testExecuteReturnsTypedRows(): void
    {
        $connector = new Connector(new ConnectionConfig(path: $this->fixturePath));
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

    public function testExecuteWithBoundParams(): void
    {
        $connector = new Connector(new ConnectionConfig(path: $this->fixturePath));
        $connector->connect();

        try {
            $result = $connector->execute(
                'SELECT id FROM customers WHERE email = :email',
                ['email' => 'b@example.com'],
            );
        } finally {
            $connector->close();
        }

        $this->assertSame(1, $result['row_count']);
        $this->assertSame(2, (int) $result['rows'][0]['id']);
    }

    public function testExecuteRejectsUnsafeSql(): void
    {
        $connector = new Connector(new ConnectionConfig(path: $this->fixturePath));
        $connector->connect();

        try {
            $connector->execute('DROP TABLE customers');
            $this->fail('Expected ConnectorException');
        } catch (ConnectorException $e) {
            $this->assertStringContainsString('safety check', $e->getMessage());
        } finally {
            $connector->close();
        }
    }

    public function testReadOnlyRejectsWrites(): void
    {
        $connector = new Connector(new ConnectionConfig(
            path: $this->fixturePath,
            readOnly: true,
        ));
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

    public function testInMemorySession(): void
    {
        $connector = new Connector(new ConnectionConfig(path: ':memory:'));
        $connector->connect();

        try {
            // Same session: introspect the empty in-memory db, then verify
            // execute() works against it via a SELECT.
            $raw = $connector->introspect();
            $this->assertSame(['tables' => []], $raw);

            $result = $connector->execute('SELECT 1 AS one');
            $this->assertSame(['one'], $result['columns']);
            $this->assertSame(1, $result['row_count']);
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
}
