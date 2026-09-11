<?php

namespace Clearsoft\EasySQL\SDK\Models;

class UsageBucket
{
    public int $used;
    public int $limit;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->used = (int) ($data['used'] ?? 0);
        $instance->limit = (int) ($data['limit'] ?? 0);
        return $instance;
    }
}
