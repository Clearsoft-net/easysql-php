<?php

namespace Clearsoft\EasySQL\SDK\Models;

class PlanResponse
{
    public string $id;
    public string $name;
    public int $price;
    public int $max_connections;
    public int $max_queries_monthly;
    public ?bool $is_active;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->id = (string) ($data['id'] ?? '');
        $instance->name = (string) ($data['name'] ?? '');
        $instance->price = (int) ($data['price'] ?? 0);
        $instance->max_connections = (int) ($data['max_connections'] ?? 0);
        $instance->max_queries_monthly = (int) ($data['max_queries_monthly'] ?? 0);
        $instance->is_active = (bool) ($data['is_active'] ?? false);
        return $instance;
    }
}
