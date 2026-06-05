<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ConnectorTestResponse
{
    public ?bool $success;
    public ?string $message;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->success = (bool) ($data['success'] ?? false);
        $instance->message = (string) ($data['message'] ?? '');
        return $instance;
    }
}
