<?php

declare(strict_types=1);

namespace Clearsoft\EasySql\Laravel\Tests;

use Clearsoft\EasySQL\SDK\Client;
use Clearsoft\EasySql\Laravel\EasySqlManager;
use Clearsoft\EasySql\Laravel\Facades\EasySQL;

class EasySqlManagerTest extends TestCase
{
    /** @test */
    public function it_resolves_the_manager_from_the_container(): void
    {
        $manager = $this->app->make(EasySqlManager::class);

        $this->assertInstanceOf(EasySqlManager::class, $manager);
    }

    /** @test */
    public function it_resolves_the_manager_via_the_easysql_alias(): void
    {
        $manager = $this->app->make("easysql");

        $this->assertInstanceOf(EasySqlManager::class, $manager);
    }

    /** @test */
    public function it_returns_the_default_connection_name(): void
    {
        $manager = $this->app->make(EasySqlManager::class);

        $this->assertSame("default", $manager->getDefaultConnection());
    }

    /** @test */
    public function it_allows_setting_the_default_connection_name(): void
    {
        $manager = $this->app->make(EasySqlManager::class);

        $manager->setDefaultConnection("analytics");

        $this->assertSame("analytics", $manager->getDefaultConnection());
    }

    /** @test */
    public function it_throws_an_exception_for_undefined_connections(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "EasySql connection [undefined] is not defined.",
        );

        $manager = $this->app->make(EasySqlManager::class);
        $manager->client("undefined");
    }

    /** @test */
    public function it_returns_a_client_instance(): void
    {
        $manager = $this->app->make(EasySqlManager::class);
        $client = $manager->client();

        $this->assertInstanceOf(Client::class, $client);
    }

    /** @test */
    public function it_returns_the_same_client_instance_on_subsequent_calls(): void
    {
        $manager = $this->app->make(EasySqlManager::class);

        $client1 = $manager->client();
        $client2 = $manager->client();

        $this->assertSame($client1, $client2);
    }

    /** @test */
    public function it_resolves_client_via_container(): void
    {
        $client = $this->app->make(Client::class);

        $this->assertInstanceOf(Client::class, $client);
    }

    /** @test */
    public function it_resolves_client_via_easysql_client_alias(): void
    {
        $client = $this->app->make("easysql.client");

        $this->assertInstanceOf(Client::class, $client);
    }

    /** @test */
    public function the_facade_resolves_the_manager(): void
    {
        $this->assertInstanceOf(
            EasySqlManager::class,
            EasySQL::getFacadeRoot(),
        );
    }
}
