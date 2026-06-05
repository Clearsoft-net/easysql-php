<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ConnectorTestRequest
{
    public string $type;
    public string $config;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->type = (string) ($data['type'] ?? '');
        $instance->config = (string) ($data['config'] ?? '');
        return $instance;
    }
}
