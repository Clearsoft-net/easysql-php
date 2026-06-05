<?php

namespace Clearsoft\EasySQL\SDK\Models;

class HTTPValidationError
{
    public ?string $detail;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->detail = (string) ($data['detail'] ?? []);
        return $instance;
    }
}
