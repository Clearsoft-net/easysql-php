<?php

namespace Clearsoft\EasySQL\SDK\Models;

class TokenRefresh
{
    public string $refresh_token;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->refresh_token = (string) ($data['refresh_token'] ?? '');
        return $instance;
    }
}
