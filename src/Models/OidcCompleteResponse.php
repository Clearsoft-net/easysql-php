<?php

namespace Clearsoft\EasySQL\SDK\Models;

class OidcCompleteResponse
{
    public string $access_token;
    public string $refresh_token;
    public string $token_type;
    public string $user;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->access_token = (string) ($data['access_token'] ?? '');
        $instance->refresh_token = (string) ($data['refresh_token'] ?? '');
        $instance->token_type = (string) ($data['token_type'] ?? '');
        $instance->user = (string) ($data['user'] ?? '');
        return $instance;
    }
}
