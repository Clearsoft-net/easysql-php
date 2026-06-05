<?php

namespace Clearsoft\EasySQL\SDK\Models;

class TokenResponse
{
    public string $access_token;
    public string $refresh_token;
    public ?string $token_type;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->access_token = (string) ($data['access_token'] ?? '');
        $instance->refresh_token = (string) ($data['refresh_token'] ?? '');
        $instance->token_type = (string) ($data['token_type'] ?? '');
        return $instance;
    }
}
