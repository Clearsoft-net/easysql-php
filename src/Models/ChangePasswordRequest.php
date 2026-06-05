<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ChangePasswordRequest
{
    public string $current_password;
    public string $new_password;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->current_password = (string) ($data['current_password'] ?? '');
        $instance->new_password = (string) ($data['new_password'] ?? '');
        return $instance;
    }
}
