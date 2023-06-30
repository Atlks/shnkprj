<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/28
 * Time: 下午6:33
 */

namespace EasySwoole\EasySwoole;


use App\Task\CheckAccountUdid;
use App\Task\ClearLog;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager as TaskManagerAsync;
use EasySwoole\MysqliPool\Mysql;
use EasySwoole\Mysqli\Config as MysqlConfig;
use EasySwoole\RedisPool\Redis;
use EasySwoole\RedisPool\Config as RedisConfig;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Component\Timer;
use App\Task\SendMessageLoop;
use App\Task\SendMessageLoopProxy;
use App\Task\TaskKeyWords;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;

class EasySwooleEvent implements Event
{

    public static function initialize()
    {
        // TODO: Implement initialize() method.
        date_default_timezone_set('Asia/Shanghai');
        defined('ROOT_PATH') or define('ROOT_PATH', __DIR__ );
        $mc =  Config::getInstance()->getConf('MYSQL');
        ################### MYSQL   #######################
        $config = new MysqlConfig($mc);
        $db = Mysql::getInstance()->register('mysql',$config);
        if ($db === null) {
            //当返回null时,代表注册失败,无法进行再次的配置修改
            //注册失败不一定要抛出异常,因为内部实现了自动注册,不需要注册也能使用
            throw new \Exception('注册失败!');
        }
        //设置其他参数
        $db->setMaxObjectNum($mc['POOL_MAX_NUM']);
        $db->setMinObjectNum($mc['POOL_MIN_NUM']);

        $proxy_mc = Config::getInstance()->getConf('PROXY_MYSQL');
        $proxy_config = new MysqlConfig($proxy_mc);
        $db_proxy = Mysql::getInstance()->register('mysqlProxy',$proxy_config);
        if ($db_proxy === null) {
            //当返回null时,代表注册失败,无法进行再次的配置修改
            //注册失败不一定要抛出异常,因为内部实现了自动注册,不需要注册也能使用
            throw new \Exception('注册失败!');
        }
        //设置其他参数
        $db_proxy->setMaxObjectNum($proxy_mc['POOL_MAX_NUM']);
        $db_proxy->setMinObjectNum($proxy_mc['POOL_MIN_NUM']);
    }

    public static function mainServerCreate(EventRegister $register)
    {
        // TODO: Implement mainServerCreate() method.

    }

    public static function onRequest(Request $request, Response $response): bool
    {
        // TODO: Implement onRequest() method.
        return true;
    }

    public static function afterRequest(Request $request, Response $response): void
    {
        // TODO: Implement afterAction() method.
    }
}