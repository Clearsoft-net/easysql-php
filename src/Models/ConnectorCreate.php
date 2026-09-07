<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ConnectorCreate
{
    public string $type;
    public string $name;
    public ?string $schema;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->type = (string) ($data['type'] ?? '');
        $instance->name = (string) ($data['name'] ?? '');
        $instance->schema = (string) ($data['schema'] ?? []);
        return $instance;
    }
}
