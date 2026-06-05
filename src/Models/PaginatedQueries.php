<?php

namespace Clearsoft\EasySQL\SDK\Models;

class PaginatedQueries
{
    public string $items;
    public int $total;
    public int $page;
    public int $per_page;
    public int $total_pages;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->items = (string) ($data['items'] ?? []);
        $instance->total = (int) ($data['total'] ?? 0);
        $instance->page = (int) ($data['page'] ?? 0);
        $instance->per_page = (int) ($data['per_page'] ?? 0);
        $instance->total_pages = (int) ($data['total_pages'] ?? 0);
        return $instance;
    }
}
