<?php

declare(strict_types=1);

namespace Clearsoft\EasySql\Laravel\Tests;

use Clearsoft\EasySql\Laravel\EasySqlServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function testit_registers_the_service_provider(): void
    {
        $providers = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(EasySqlServiceProvider::class, $providers);
        $this->assertTrue($providers[EasySqlServiceProvider::class]);
    }
    public function testit_merges_the_package_config(): void
    {
        $config = $this->app["config"]->get("easysql");

        $this->assertIsArray($config);
        $this->assertArrayHasKey("default", $config);
        $this->assertArrayHasKey("connections", $config);
        $this->assertArrayHasKey("default", $config["connections"]);
    }
    public function testit_has_expected_config_structure(): void
    {
        $connection = $this->app["config"]->get("easysql.connections.default");

        $this->assertArrayHasKey("base_url", $connection);
        $this->assertArrayHasKey("access_token", $connection);
        $this->assertArrayHasKey("timeout", $connection);
    }
    public function testit_defines_the_config_publishable_tag(): void
    {
        $provider = $this->app->getProvider(EasySqlServiceProvider::class);

        $this->assertNotNull($provider);

        $this->assertFileExists(dirname(__DIR__, 2) . "/laravel/config/easysql.php");
    }
}
