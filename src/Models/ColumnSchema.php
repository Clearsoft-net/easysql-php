<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ColumnSchema
{
    public string $name;
    public string $type;
    public ?bool $nullable;
    public ?bool $primary_key;
    public ?string $default;
    public ?string $foreign_key;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->name = (string) ($data['name'] ?? '');
        $instance->type = (string) ($data['type'] ?? '');
        $instance->nullable = (bool) ($data['nullable'] ?? false);
        $instance->primary_key = (bool) ($data['primary_key'] ?? false);
        $instance->default = (string) ($data['default'] ?? '');
        $instance->foreign_key = (string) ($data['foreign_key'] ?? '');
        return $instance;
    }
}
