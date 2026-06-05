<?php

namespace Clearsoft\EasySQL\SDK\Models;

class QueryHistoryItem
{
    public string $id;
    public string $connector_id;
    public string $question;
    public string $sql_generated;
    public string $answer;
    public string $error;
    public string $created_at;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->id = (string) ($data['id'] ?? '');
        $instance->connector_id = (string) ($data['connector_id'] ?? '');
        $instance->question = (string) ($data['question'] ?? '');
        $instance->sql_generated = (string) ($data['sql_generated'] ?? '');
        $instance->answer = (string) ($data['answer'] ?? '');
        $instance->error = (string) ($data['error'] ?? '');
        $instance->created_at = (string) ($data['created_at'] ?? '');
        return $instance;
    }
}
