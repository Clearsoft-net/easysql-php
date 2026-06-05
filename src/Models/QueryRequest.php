<?php

namespace Clearsoft\EasySQL\SDK\Models;

class QueryRequest
{
    public string $connector_id;
    public string $question;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->connector_id = (string) ($data['connector_id'] ?? '');
        $instance->question = (string) ($data['question'] ?? '');
        return $instance;
    }
}
