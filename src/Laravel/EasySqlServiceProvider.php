<?php

declare(strict_types=1);

namespace Clearsoft\EasySql\Laravel;

use Clearsoft\EasySQL\SDK\Client;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class EasySqlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2) . "/config/easysql.php", "easysql");

        $this->app->singleton(EasySqlManager::class, function (
            Application $app,
        ) {
            return new EasySqlManager($app["config"]["easysql"]);
        });

        $this->app->singleton(Client::class, function (Application $app) {
            return $app[EasySqlManager::class]->client();
        });

        $this->app->alias(EasySqlManager::class, "easysql");
        $this->app->alias(Client::class, "easysql.client");
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [
                    dirname(__DIR__, 2) .
                    "/config/easysql.php" => $this->app->configPath(
                        "easysql.php",
                    ),
                ],
                "easysql-config",
            );
        }
    }
}
