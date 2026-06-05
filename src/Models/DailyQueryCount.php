<?php

namespace Clearsoft\EasySQL\SDK\Models;

class DailyQueryCount
{
    public string $date;
    public int $count;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->date = (string) ($data['date'] ?? '');
        $instance->count = (int) ($data['count'] ?? 0);
        return $instance;
    }
}
