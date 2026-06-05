<?php

namespace Clearsoft\EasySQL\SDK\Models;

class UserResponse
{
    public string $id;
    public string $email;
    public string $name;
    public string $locale;
    public string $created_at;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->id = (string) ($data['id'] ?? '');
        $instance->email = (string) ($data['email'] ?? '');
        $instance->name = (string) ($data['name'] ?? '');
        $instance->locale = (string) ($data['locale'] ?? '');
        $instance->created_at = (string) ($data['created_at'] ?? '');
        return $instance;
    }
}
