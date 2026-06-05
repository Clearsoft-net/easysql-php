<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ApiKeyCreate
{
    public string $name;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->name = (string) ($data['name'] ?? '');
        return $instance;
    }
}
