<?php

namespace Clearsoft\EasySQL\SDK\Models;

class CheckoutResponse
{
    public string $url;

    /**
     * @param array $data Raw API response data.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->url = (string) ($data['url'] ?? '');
        return $instance;
    }
}
