<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ConnectorConfig
{
    public string $host;
    public int $port;
    public string $user;
    public string $password;
    public string $database;
    public ?bool $ssl;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->host = (string) ($data['host'] ?? '');
        $instance->port = (int) ($data['port'] ?? 0);
        $instance->user = (string) ($data['user'] ?? '');
        $instance->password = (string) ($data['password'] ?? '');
        $instance->database = (string) ($data['database'] ?? '');
        $instance->ssl = (bool) ($data['ssl'] ?? false);
        return $instance;
    }
}
