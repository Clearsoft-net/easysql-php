<?php

namespace Clearsoft\EasySQL\SDK\Models;

class OidcLogoutResponse
{
    public string $end_session_url;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->end_session_url = (string) ($data['end_session_url'] ?? '');
        return $instance;
    }
}
