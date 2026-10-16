<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Tests\Postgres;

use Clearsoft\EasySQL\Connectors\ConnectorEngine;
use Clearsoft\EasySQL\Connectors\ConnectorException;
use Clearsoft\EasySQL\Connectors\ConnectorFactory;
use Clearsoft\EasySQL\Connectors\Postgres\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\Postgres\Connector;
use Clearsoft\EasySQL\Connectors\Postgres\NeonHttpSession;
use Clearsoft\EasySQL\Connectors\Postgres\Queries;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    // ── Config ────────────────────────────────────────────────

    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame('127.0.0.1', $config->host);
        $this->assertSame(5432, $config->port);
        $this->assertNull($config->socket);
        $this->assertSame('prefer', $config->sslmode);
        $this->assertSame(['public'], $config->schemas);
        $this->assertSame(10.0, $config->timeout);
        $this->assertNull($config->connectionString);
        $this->assertSame(ConnectionConfig::PROVIDER_POSTGRES, $config->provider);
        $this->assertSame(ConnectionConfig::TRANSPORT_TCP, $config->transport);
    }

    // ── Provider-aware catalog queries ────────────────────────

    public function testTablesQueryOmitsRelispartitionForRedshift(): void
    {
        $this->assertStringContainsString('relispartition', Queries::tables('postgres'));
        $this->assertStringContainsString('relispartition', Queries::tables('cockroachdb'));
        $this->assertStringNotContainsString('relispartition', Queries::tables('redshift'));
    }

    public function testDsnFormat(): void
    {
        $config = new ConnectionConfig(
            host: 'db.internal',
            port: 5433,
            database: 'shop',
            sslmode: 'require',
        );

        $this->assertSame(
            'pgsql:host=db.internal;port=5433;dbname=shop;sslmode=require',
            $config->toDsn(),
        );
    }

    public function testDsnWithUnixSocket(): void
    {
        $config = new ConnectionConfig(
            socket: '/var/run/postgresql',
            database: 'shop',
        );

        $this->assertSame(
            'pgsql:host=/var/run/postgresql;port=5432;dbname=shop;sslmode=prefer',
            $config->toDsn(),
        );
    }

    public function testSocketTakesPrecedenceOverHostAndKeepsThePort(): void
    {
        $config = new ConnectionConfig(
            host: 'ignored-if-socket',
            port: 5433,
            socket: '/var/run/postgresql',
            database: 'shop',
        );

        $this->assertSame(
            'pgsql:host=/var/run/postgresql;port=5433;dbname=shop;sslmode=prefer',
            $config->toDsn(),
        );
    }

    public function testFromUrlMapsSslToSslmode(): void
    {
        $plain = ConnectionConfig::fromUrl('postgresql://postgres:secret@db.internal:5433/shop');
        $this->assertSame('prefer', $plain->sslmode);
        $this->assertSame(5433, $plain->port);
        $this->assertSame('shop', $plain->database);

        $secure = ConnectionConfig::fromUrl('postgresql://postgres:secret@db.internal/shop?sslmode=require');
        $this->assertSame('require', $secure->sslmode);
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
            $this->assertStringContainsString('PostgreSQL', $e->getMessage());
        }
    }

    public function testExecuteWithoutConnectThrows(): void
    {
        $connector = new Connector(new ConnectionConfig());

        $this->expectException(\RuntimeException::class);

        $connector->execute('SELECT 1');
    }

    // ── Transports ────────────────────────────────────────────

    public function testFactoryBuildsANeonHttpConnectorFromAnOptionsArray(): void
    {
        $connector = ConnectorFactory::create(ConnectorEngine::PostgreSQL, [
            'transport' => ConnectionConfig::TRANSPORT_HTTP,
            'connectionString' => 'postgresql://u:p@ep-x.us-east-2.aws.neon.tech/db',
        ]);

        $this->assertInstanceOf(Connector::class, $connector);
    }

    public function testWebsocketTransportIsUnsupported(): void
    {
        $connector = new Connector(new ConnectionConfig(
            transport: ConnectionConfig::TRANSPORT_WEBSOCKET,
            connectionString: 'postgresql://u:p@ep-x.us-east-2.aws.neon.tech/db',
        ));

        try {
            $connector->connect();
            $this->fail('Expected ConnectorException');
        } catch (ConnectorException $e) {
            $this->assertStringContainsString('websocket', strtolower($e->getMessage()));
        }
    }

    public function testHttpTransportRequiresAConnectionString(): void
    {
        $connector = new Connector(new ConnectionConfig(transport: ConnectionConfig::TRANSPORT_HTTP));

        try {
            $connector->connect();
            $this->fail('Expected ConnectorException');
        } catch (ConnectorException $e) {
            $this->assertStringContainsString('connection string', $e->getMessage());
        }
    }

    public function testNeonHttpSessionSendsConnectionStringAndParsesRows(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($server, $errstr);
        $address = stream_socket_get_name($server, false);
        $port = (int) substr(strrchr((string) $address, ':'), 1);
        $capture = sys_get_temp_dir() . '/easysql_neon_req_' . getmypid() . '.txt';

        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid);
        if ($pid === 0) {
            $client = @stream_socket_accept($server, 10);
            if ($client !== false) {
                $request = '';
                while (!str_contains($request, "\r\n\r\n")) {
                    $chunk = fread($client, 8192);
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    $request .= $chunk;
                }
                preg_match('/Content-Length: (\d+)/i', $request, $match);
                $length = (int) ($match[1] ?? 0);
                $bodyStart = strpos($request, "\r\n\r\n") + 4;
                $body = substr($request, $bodyStart);
                while (strlen($body) < $length) {
                    $body .= fread($client, $length - strlen($body));
                }
                file_put_contents($capture, $request . $body);

                $response = json_encode([
                    'fields' => [['name' => 'id'], ['name' => 'email']],
                    'rows' => [['id' => '1', 'email' => 'a@example.com']],
                    'rowCount' => 1,
                ]);
                fwrite($client, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen((string) $response) . "\r\nConnection: close\r\n\r\n{$response}");
                fclose($client);
            }
            exit(0);
        }

        fclose($server);
        usleep(100_000);

        $session = new NeonHttpSession('postgresql://u:p@host/db', "http://127.0.0.1:{$port}/sql");
        $session->open();
        $result = $session->query('SELECT id, email FROM users');

        pcntl_waitpid($pid, $status);
        $sent = (string) file_get_contents($capture);
        @unlink($capture);

        $this->assertStringContainsString('Neon-Connection-String: postgresql://u:p@host/db', $sent);
        $this->assertStringContainsString('SELECT id, email FROM users', $sent);
        $this->assertSame(['id', 'email'], $result['columns']);
        $this->assertSame('a@example.com', $result['rows'][0]['email']);
    }

    // ── Integration (requires local PostgreSQL) ───────────────

    private function integrationConfig(): ConnectionConfig
    {
        return new ConnectionConfig(
            host: getenv('EASYSQL_TEST_POSTGRES_HOST') ?: '127.0.0.1',
            port: (int) (getenv('EASYSQL_TEST_POSTGRES_PORT') ?: 5433),
            user: getenv('EASYSQL_TEST_POSTGRES_USER') ?: 'postgres',
            password: getenv('EASYSQL_TEST_POSTGRES_PASSWORD') ?: 'postgres',
            database: getenv('EASYSQL_TEST_POSTGRES_DATABASE') ?: 'easysql_test',
            sslmode: getenv('EASYSQL_TEST_POSTGRES_SSLMODE') ?: 'prefer',
            schemas: ['public'],
        );
    }

    public function testIntegrationAgainstLocalPostgres(): void
    {
        if (getenv('EASYSQL_TEST_POSTGRES') !== '1') {
            $this->markTestSkipped('Set EASYSQL_TEST_POSTGRES=1 with a local PostgreSQL to run integration tests.');
        }

        $connector = new Connector($this->integrationConfig());
        $connector->connect();

        try {
            $raw = $connector->introspect();
            $this->assertSame('postgresql', $raw['engine']);
            $this->assertArrayHasKey('tables', $raw);

            $tables = array_column($raw['tables'], 'name');
            $this->assertContains('customers', $tables);
            $this->assertContains('orders', $tables);

            $orders = null;
            foreach ($raw['tables'] as $table) {
                if ($table['name'] === 'orders') {
                    $orders = $table;
                }
            }
            $this->assertNotNull($orders);

            $customerId = null;
            foreach ($orders['columns'] as $column) {
                if ($column['name'] === 'customer_id') {
                    $customerId = $column;
                }
            }
            $this->assertNotNull($customerId);
            $this->assertSame(
                ['table' => 'customers', 'column' => 'id'],
                $customerId['foreignKey'],
            );

            $result = $connector->execute('SELECT id, email FROM customers ORDER BY id');
            $this->assertSame(['id', 'email'], $result['columns']);
            $this->assertSame(2, $result['row_count']);
        } finally {
            $connector->close();
        }
    }

    public function testIntegrationOverUnixSocket(): void
    {
        $socket = getenv('EASYSQL_TEST_POSTGRES_SOCKET');
        if ($socket === false || $socket === '') {
            $this->markTestSkipped('Set EASYSQL_TEST_POSTGRES_SOCKET with a local PostgreSQL socket to run this test.');
        }

        $connector = new Connector(new ConnectionConfig(
            socket: $socket,
            user: getenv('EASYSQL_TEST_POSTGRES_USER') ?: 'postgres',
            password: getenv('EASYSQL_TEST_POSTGRES_PASSWORD') ?: 'postgres',
            database: getenv('EASYSQL_TEST_POSTGRES_DATABASE') ?: 'easysql_test',
            schemas: ['public'],
        ));
        $connector->connect();

        try {
            $result = $connector->execute('SELECT 1 AS one');
            $this->assertSame(['one'], $result['columns']);
            $this->assertSame(1, $result['row_count']);
        } finally {
            $connector->close();
        }
    }
}
