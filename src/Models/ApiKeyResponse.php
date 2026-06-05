<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ApiKeyResponse
{
    public string $id;
    public string $name;
    public string $prefix;
    public string $last_used_at;
    public bool $is_active;
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
        $instance->last_used_at = (string) ($data['last_used_at'] ?? '');
        $instance->is_active = (bool) ($data['is_active'] ?? false);
        $instance->created_at = (string) ($data['created_at'] ?? '');
        return $instance;
    }
}
