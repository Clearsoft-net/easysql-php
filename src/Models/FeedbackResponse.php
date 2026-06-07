<?php

namespace Clearsoft\EasySQL\SDK\Models;

class FeedbackResponse
{
    public string $id;
    public string $query_id;
    public bool $positive;
    public string $comment;
    public string $created_at;
    public string $updated_at;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->id = (string) ($data['id'] ?? '');
        $instance->query_id = (string) ($data['query_id'] ?? '');
        $instance->positive = (bool) ($data['positive'] ?? false);
        $instance->comment = (string) ($data['comment'] ?? '');
        $instance->created_at = (string) ($data['created_at'] ?? '');
        $instance->updated_at = (string) ($data['updated_at'] ?? '');
        return $instance;
    }
}
