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
 * @method static array createConnection(array $body)
 * @method static void deleteConnection(string $connection_id)
 * @method static array getConnection(string $connection_id)
 * @method static array listConnections()
 * @method static array syncConnection(string $connection_id)
 * @method static array updateConnection(array $body, string $connection_id)
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
