<?php

namespace Clearsoft\EasySQL\SDK\Models;

class SuggestionsResponse
{
    public string $suggestions;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->suggestions = (string) ($data['suggestions'] ?? []);
        return $instance;
    }
}
