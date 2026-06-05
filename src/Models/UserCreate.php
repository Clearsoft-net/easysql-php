<?php

namespace Clearsoft\EasySQL\SDK\Models;

class UserCreate
{
    public string $email;
    public string $password;
    public string $name;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->email = (string) ($data['email'] ?? '');
        $instance->password = (string) ($data['password'] ?? '');
        $instance->name = (string) ($data['name'] ?? '');
        return $instance;
    }
}
