<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ApiKeyCreated
{
    public string $id;
    public string $name;
    public string $prefix;
    public string $key;
    public string $created_at;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->id = (string) ($data['id'] ?? '');
        $instance->name = (string) ($data['name'] ?? '');
        $instance->prefix = (string) ($data['prefix'] ?? '');
        $instance->key = (string) ($data['key'] ?? '');
        $instance->created_at = (string) ($data['created_at'] ?? '');
        return $instance;
    }
}
