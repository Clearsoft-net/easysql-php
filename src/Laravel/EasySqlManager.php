<?php

declare(strict_types=1);

namespace Clearsoft\EasySql\Laravel;

use Clearsoft\EasySQL\SDK\Client;
use InvalidArgumentException;

/**
 * @mixin \Clearsoft\EasySQL\SDK\Client
 */
class EasySqlManager
{
    /** @var array<string, Client> */
    protected array $connections = [];

    protected string $defaultConnection;

    public function __construct(protected readonly array $config)
    {
        $this->defaultConnection = $config["default"] ?? "default";
    }

    public function client(?string $name = null): Client
    {
        $name = $name ?: $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);
        }

        return $this->connections[$name];
    }

    protected function resolve(string $name): Client
    {
        $connectionConfig = $this->config["connections"][$name] ?? null;

        if ($connectionConfig === null) {
            throw new InvalidArgumentException(
                "EasySql connection [{$name}] is not defined.",
            );
        }

        return new Client($connectionConfig);
    }

    public function getDefaultConnection(): string
    {
        return $this->defaultConnection;
    }

    public function setDefaultConnection(string $name): void
    {
        $this->defaultConnection = $name;
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->client()->$method(...$parameters);
    }
}
