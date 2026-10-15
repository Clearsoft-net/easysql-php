<?php

declare(strict_types=1);

namespace Clearsoft\EasySQL\Connectors\Postgres;

use Clearsoft\EasySQL\Common\ConnectionUrl;
use PDO;

/**
 * The PostgreSQL wire protocol over TCP or a Unix socket, via PDO.
 *
 * When a full connection string is supplied it takes precedence over the
 * explicit host/port/user/password/database fields (mirroring the TypeScript
 * `TcpSession`); otherwise the session is built from the config fields.
 */
final class TcpSession implements PgSession
{
    private ?PDO $pdo = null;

    public function __construct(private readonly ConnectionConfig $config)
    {
    }

    public function open(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => (int) ceil($this->config->timeout),
        ];

        if ($this->config->connectionString !== null) {
            $parsed = ConnectionUrl::parse($this->config->connectionString);
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
                $parsed['host'],
                $parsed['port'],
                $parsed['database'],
                $parsed['ssl'] ? 'require' : $this->config->sslmode,
            );
            $user = $parsed['user'];
            $password = $parsed['password'];
        } else {
            $dsn = $this->config->toDsn();
            $user = $this->config->user;
            $password = $this->config->password;
        }

        $this->pdo = new PDO($dsn, $user, $password, $options);
    }

    public function query(string $sql, array $params = []): array
    {
        if ($this->pdo === null) {
            throw new \RuntimeException('PostgreSQL session is not open.');
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'columns' => $rows === [] ? [] : array_keys($rows[0]),
            'rows' => $rows,
        ];
    }

    public function close(): void
    {
        // PDO releases its connection when the reference is dropped.
        $this->pdo = null;
    }
}
