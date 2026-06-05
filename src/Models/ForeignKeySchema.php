<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ForeignKeySchema
{
    public string $table;
    public string $column;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->table = (string) ($data['table'] ?? '');
        $instance->column = (string) ($data['column'] ?? '');
        return $instance;
    }
}
