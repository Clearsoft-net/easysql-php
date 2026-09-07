<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ConnectorSyncRequest
{
    public string $schema;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->schema = (string) ($data['schema'] ?? []);
        return $instance;
    }
}
