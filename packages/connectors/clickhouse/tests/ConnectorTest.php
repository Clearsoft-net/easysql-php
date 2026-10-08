<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\ClickHouse\Tests;

use Clearsoft\EasySQL\Connectors\ClickHouse\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\ClickHouse\Connector;
use Clearsoft\EasySQL\Connectors\ClickHouse\ConnectorException;
use Clearsoft\EasySQL\Common\SqlValidator;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    // ── Config ────────────────────────────────────────────────

    public function testConfigDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertSame('127.0.0.1', $config->host);
        $this->assertSame(8123, $config->port);
        $this->assertSame('default', $config->user);
        $this->assertFalse($config->ssl);
        $this->assertSame(10.0, $config->timeout);
    }

    public function testSslDefaultsToTheSecurePort(): void
    {
        $config = new ConnectionConfig(host: 'clickhouse.internal', ssl: true);

        $this->assertSame(8443, $config->port);
        $this->assertSame('https://clickhouse.internal:8443', $config->baseUrl());
    }

    public function testExplicitPortOverridesTheDefault(): void
    {
        $config = new ConnectionConfig(host: 'clickhouse.internal', port: 9000);

        $this->assertSame(9000, $config->port);
        $this->assertSame('http://clickhouse.internal:9000', $config->baseUrl());
    }

    // ── Validator ─────────────────────────────────────────────

    public function testValidatorAcceptsSelect(): void
    {
        $this->assertTrue(SqlValidator::validateSelectOnly('SELECT * FROM events LIMIT 10')['ok']);
    }

    public function testValidatorRejectsMutations(): void
    {
        foreach (['DELETE FROM events', 'DROP TABLE events', 'INSERT INTO events VALUES (1)'] as $sql) {
            $this->assertFalse(SqlValidator::validateSelectOnly($sql)['ok'], $sql);
        }
    }

    // ── Connection failures ───────────────────────────────────

    public function testConnectionFailureStripsCredentials(): void
    {
        $connector = new Connector(new ConnectionConfig(
            host: '127.0.0.1',
            port: 1, // closed port — connection must fail fast
            user: 'secret_user',
            password: 's3cr3t-p4ss',
            database: 'default',
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

    public function testConnectorExceptionSanitizesCredentialsFromClickhouseErrors(): void
    {
        $connector = new Connector(new ConnectionConfig(
            host: '127.0.0.1',
            port: 1,
            user: 'secret_user',
            password: 'secret_password',
            timeout: 1.0,
        ));

        try {
            $connector->connect();
            $this->fail('Expected ConnectorException');
        } catch (ConnectorException $e) {
            $this->assertStringNotContainsString('secret_password', $e->getMessage());
            $this->assertStringNotContainsString('secret_user', $e->getMessage());
        }
    }

    public function testIntrospectWithoutConnectThrows(): void
    {
        $connector = new Connector(new ConnectionConfig());

        $this->expectException(\RuntimeException::class);

        $connector->introspect();
    }

    // ── Integration (requires local ClickHouse) ───────────────

    public function testHttpMockIntrospectsAndExecutes(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($server, $errstr);
        $address = stream_socket_get_name($server, false);
        $port = (int) substr(strrchr((string) $address, ':'), 1);

        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid);
        if ($pid === 0) {
            for ($i = 0; $i < 5; $i++) {
                $client = @stream_socket_accept($server, 5);
                if ($client === false) {
                    continue;
                }
                $request = '';
                while (!str_contains($request, "\r\n\r\n")) {
                    $chunk = fread($client, 8192);
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    $request .= $chunk;
                }
                preg_match('/^POST \\S+ HTTP\\/[^\\r\\n]+/', $request, $match);
                preg_match('/Content-Length: (\\d+)/i', $request, $lengthMatch);
                $bodyStart = strpos($request, "\r\n\r\n") + 4;
                $body = substr($request, $bodyStart);
                $length = (int) ($lengthMatch[1] ?? 0);
                while (strlen($body) < $length) {
                    $body .= fread($client, $length - strlen($body));
                }
                $path = explode(' ', $match[0] ?? '')[1] ?? '/';
                if ($path === '/ping') {
                    $response = 'Ok.';
                    $contentType = 'text/plain';
                } elseif (str_contains($body, 'system.tables')) {
                    $response = json_encode(['table_name' => 'events', 'rows_approx' => '3']) . "\n";
                    $contentType = 'text/plain';
                } elseif (str_contains($body, 'system.columns')) {
                    $response = json_encode([
                        'column_name' => 'id',
                        'data_type' => 'UInt64',
                        'column_default' => '',
                        'is_primary' => 1,
                        'ordinal' => 1,
                    ]) . "\n";
                    $contentType = 'text/plain';
                } else {
                    $response = json_encode([
                        'meta' => [['name' => 'one', 'type' => 'UInt8']],
                        'data' => [['one' => 1]],
                        'rows' => 1,
                    ]);
                    $contentType = 'application/json';
                }
                fwrite($client, "HTTP/1.1 200 OK\r\nContent-Type: {$contentType}\r\nContent-Length: " . strlen((string) $response) . "\r\nConnection: close\r\n\r\n{$response}");
                fclose($client);
            }
            exit(0);
        }

        fclose($server);
        usleep(100_000);
        $connector = new Connector(new ConnectionConfig(port: $port));
        try {
            $connector->connect();
            $schema = $connector->introspect();
            $result = $connector->execute('SELECT 1 AS one');
        } finally {
            $connector->close();
            pcntl_waitpid($pid, $status);
        }

        $this->assertSame('clickhouse', $schema['engine']);
        $this->assertSame('UInt64', $schema['tables'][0]['columns'][0]['dataType']);
        $this->assertSame(1, $result['rows'][0]['one']);
    }

    public function testIntegrationAgainstLocalClickhouse(): void
    {
        if (getenv('EASYSQL_TEST_CLICKHOUSE') !== '1') {
            $this->markTestSkipped('Set EASYSQL_TEST_CLICKHOUSE=1 with a local ClickHouse to run integration tests.');
        }

        $connector = new Connector(new ConnectionConfig(
            host: getenv('EASYSQL_TEST_CLICKHOUSE_HOST') ?: '127.0.0.1',
            port: (int) (getenv('EASYSQL_TEST_CLICKHOUSE_PORT') ?: 8123),
            user: getenv('EASYSQL_TEST_CLICKHOUSE_USER') ?: 'default',
            password: getenv('EASYSQL_TEST_CLICKHOUSE_PASSWORD') ?: '',
            database: getenv('EASYSQL_TEST_CLICKHOUSE_DATABASE') ?: 'default',
        ));
        $connector->connect();

        try {
            $raw = $connector->introspect();
            $this->assertSame('clickhouse', $raw['engine']);
            $this->assertArrayHasKey('tables', $raw);

            $result = $connector->execute('SELECT 1 AS one');
            $this->assertSame(['one'], $result['columns']);
            $this->assertSame(1, $result['row_count']);
        } finally {
            $connector->close();
        }
    }
}
