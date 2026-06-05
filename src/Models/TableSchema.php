<?php

namespace Clearsoft\EasySQL\SDK\Models;

class TableSchema
{
    public string $name;
    public string $columns;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->name = (string) ($data['name'] ?? '');
        $instance->columns = (string) ($data['columns'] ?? []);
        return $instance;
    }
}
