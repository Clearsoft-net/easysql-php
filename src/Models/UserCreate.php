<?php

namespace Clearsoft\EasySQL\SDK\Models;

class UserCreate
{
    public string $name;
    public string $email;
    public string $password;
    public ?string $locale;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->name = (string) ($data['name'] ?? '');
        $instance->email = (string) ($data['email'] ?? '');
        $instance->password = (string) ($data['password'] ?? '');
        $instance->locale = (string) ($data['locale'] ?? '');
        return $instance;
    }
}
