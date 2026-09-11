<?php

namespace Clearsoft\EasySQL\SDK\Models;

class OidcCompleteRequest
{
    public string $state;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->state = (string) ($data['state'] ?? '');
        return $instance;
    }
}
