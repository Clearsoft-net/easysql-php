<?php

namespace Clearsoft\EasySQL\SDK\Models;

class QueryResponse
{
    public string $id;
    public string $question;
    public string $sql_generated;
    public string $answer;
    public string $chart_config;
    public string $error;
    public ?string $result_data;
    public string $created_at;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->id = (string) ($data['id'] ?? '');
        $instance->question = (string) ($data['question'] ?? '');
        $instance->sql_generated = (string) ($data['sql_generated'] ?? '');
        $instance->answer = (string) ($data['answer'] ?? '');
        $instance->chart_config = (string) ($data['chart_config'] ?? '');
        $instance->error = (string) ($data['error'] ?? '');
        $instance->result_data = (string) ($data['result_data'] ?? '');
        $instance->created_at = (string) ($data['created_at'] ?? '');
        return $instance;
    }
}
