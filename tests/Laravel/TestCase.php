<?php

declare(strict_types=1);

namespace Clearsoft\EasySql\Laravel\Tests;

use Clearsoft\EasySql\Laravel\EasySqlServiceProvider;
use Clearsoft\EasySql\Laravel\EasySqlManager;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [EasySqlServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app["config"]->set("easysql", [
            "default" => "default",
            "connections" => [
                "default" => [
                    "base_url" => "https://api.easysql.net",
                    "access_token" => "test-token",
                    "timeout" => 30,
                ],
            ],
        ]);
    }

    protected function getPackageAliases($app): array
    {
        return [
            "EasySQL" => \Clearsoft\EasySql\Laravel\Facades\EasySQL::class,
        ];
    }
}
