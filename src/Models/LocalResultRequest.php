<?php

namespace Clearsoft\EasySQL\SDK\Models;

class LocalResultRequest
{
    public string $result_data;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->result_data = (string) ($data['result_data'] ?? []);
        return $instance;
    }
}
