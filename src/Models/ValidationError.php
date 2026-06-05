<?php

namespace Clearsoft\EasySQL\SDK\Models;

class ValidationError
{
    public string $loc;
    public string $msg;
    public string $type;
    public ?string $input;
    public ?string $ctx;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->loc = (string) ($data['loc'] ?? []);
        $instance->msg = (string) ($data['msg'] ?? '');
        $instance->type = (string) ($data['type'] ?? '');
        $instance->input = (string) ($data['input'] ?? '');
        $instance->ctx = (string) ($data['ctx'] ?? '');
        return $instance;
    }
}
