<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ConnectorSchemaResponse
{
    public string $tables;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->tables = (string) ($data['tables'] ?? []);
        return $instance;
    }
}
