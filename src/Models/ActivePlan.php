<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ActivePlan
{
    public string $id;
    public string $name;
    public int $max_queries_daily;
    public int $max_queries_weekly;
    public int $max_queries_monthly;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->id = (string) ($data['id'] ?? '');
        $instance->name = (string) ($data['name'] ?? '');
        $instance->max_queries_daily = (int) ($data['max_queries_daily'] ?? 0);
        $instance->max_queries_weekly = (int) ($data['max_queries_weekly'] ?? 0);
        $instance->max_queries_monthly = (int) ($data['max_queries_monthly'] ?? 0);
        return $instance;
    }
}
