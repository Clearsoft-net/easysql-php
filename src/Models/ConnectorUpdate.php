<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ConnectorUpdate
{
    public ?string $name;
    public ?string $config;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->name = (string) ($data['name'] ?? '');
        $instance->config = (string) ($data['config'] ?? '');
        return $instance;
    }
}
