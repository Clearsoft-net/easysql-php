<?php

namespace Clearsoft\EasySQL\SDK\Models;

class AutocompleteRequest
{
    public string $question;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->question = (string) ($data['question'] ?? '');
        return $instance;
    }
}
