<?php

namespace Clearsoft\EasySQL\SDK\Models;

class FeedbackCreate
{
    public bool $positive;
    public ?string $comment;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->positive = (bool) ($data['positive'] ?? false);
        $instance->comment = (string) ($data['comment'] ?? '');
        return $instance;
    }
}
