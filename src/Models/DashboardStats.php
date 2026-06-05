<?php

namespace Clearsoft\EasySQL\SDK\Models;

class DashboardStats
{
    public int $active_connectors;
    public int $queries_used_this_month;
    public int $queries_limit;
    public string $queries_per_day;
    public string $most_used_connectors;
    public string $fetched_at;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->active_connectors = (int) ($data['active_connectors'] ?? 0);
        $instance->queries_used_this_month = (int) ($data['queries_used_this_month'] ?? 0);
        $instance->queries_limit = (int) ($data['queries_limit'] ?? 0);
        $instance->queries_per_day = (string) ($data['queries_per_day'] ?? []);
        $instance->most_used_connectors = (string) ($data['most_used_connectors'] ?? []);
        $instance->fetched_at = (string) ($data['fetched_at'] ?? '');
        return $instance;
    }
}
