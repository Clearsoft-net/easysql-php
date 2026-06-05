<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ConnectorUsage
{
    public string $connector_id;
    public string $connector_name;
    public int $query_count;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->connector_id = (string) ($data['connector_id'] ?? '');
        $instance->connector_name = (string) ($data['connector_name'] ?? '');
        $instance->query_count = (int) ($data['query_count'] ?? 0);
        return $instance;
    }
}
