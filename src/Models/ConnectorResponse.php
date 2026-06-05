<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ConnectorResponse
{
    public string $id;
    public string $type;
    public string $name;
    public string $last_sync_at;
    public string $created_at;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->id = (string) ($data['id'] ?? '');
        $instance->type = (string) ($data['type'] ?? '');
        $instance->name = (string) ($data['name'] ?? '');
        $instance->last_sync_at = (string) ($data['last_sync_at'] ?? '');
        $instance->created_at = (string) ($data['created_at'] ?? '');
        return $instance;
    }
}
