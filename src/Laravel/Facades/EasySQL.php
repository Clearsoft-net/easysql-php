<?php

declare(strict_types=1);

namespace Clearsoft\EasySql\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array changePassword(array $body)
 * @method static void deleteMe()
 * @method static array login(array $body)
 * @method static array me()
 * @method static array refresh(array $body)
 * @method static array register(array $body)
 * @method static array updateMe(array $body)
 * @method static array checkout(array $query = [])
 * @method static array getPlan()
 * @method static array portal()
 * @method static array createConnector(array $body)
 * @method static void deleteConnector(string $connector_id)
 * @method static array getConnector(string $connector_id)
 * @method static array listConnectors()
 * @method static array syncConnector(string $connector_id)
 * @method static array testConnector(array $body)
 * @method static array updateConnector(array $body, string $connector_id)
 * @method static array dashboardStats()
 * @method static array health()
 * @method static array healthHealth()
 * @method static array createQuery(array $body)
 * @method static array getQuery(string $query_id)
 * @method static array listQueries(array $query = [])
 *
 * @see \Clearsoft\EasySql\Laravel\EasySqlManager
 */
class EasySQL extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'easysql';
    }
}
