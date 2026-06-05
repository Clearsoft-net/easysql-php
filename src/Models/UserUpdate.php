<?php

namespace Clearsoft\EasySQL\SDK\Models;

class UserUpdate
{
    public ?string $name;
    public ?string $locale;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->name = (string) ($data['name'] ?? '');
        $instance->locale = (string) ($data['locale'] ?? '');
        return $instance;
    }
}
