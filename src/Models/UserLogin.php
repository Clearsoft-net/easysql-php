<?php

namespace Clearsoft\EasySQL\SDK\Models;

class UserLogin
{
    public string $email;
    public string $password;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->email = (string) ($data['email'] ?? '');
        $instance->password = (string) ($data['password'] ?? '');
        return $instance;
    }
}
