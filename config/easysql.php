<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default EasySQL Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the connections below you wish to use as
    | your default connection for all EasySQL work.
    |
    */

    "default" => env("EASYSQL_CONNECTION", "default"),

    /*
    |--------------------------------------------------------------------------
    | EasySQL Connections
    |--------------------------------------------------------------------------
    |
    | Each connection is passed directly to the EasySQL SDK Client constructor.
    | The SDK expects an HTTP client configuration (base_url, access_token,
    | timeout). See the SDK documentation for all available options.
    |
    */

    "connections" => [
        "default" => [
            "base_url" => env("EASYSQL_BASE_URL", "https://api.easysql.net"),
            "access_token" => env("EASYSQL_ACCESS_TOKEN"),
            "timeout" => env("EASYSQL_TIMEOUT", 30),
        ],
    ],
];
