<?php

namespace Clearsoft\EasySQL\SDK\Models;

class UsageResponse
{
    public string $daily;
    public string $weekly;
    public string $monthly;
    public string $plan_id;
    public string $plan_name;
    public string $fetched_at;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->daily = (string) ($data['daily'] ?? '');
        $instance->weekly = (string) ($data['weekly'] ?? '');
        $instance->monthly = (string) ($data['monthly'] ?? '');
        $instance->plan_id = (string) ($data['plan_id'] ?? '');
        $instance->plan_name = (string) ($data['plan_name'] ?? '');
        $instance->fetched_at = (string) ($data['fetched_at'] ?? '');
        return $instance;
    }
}
