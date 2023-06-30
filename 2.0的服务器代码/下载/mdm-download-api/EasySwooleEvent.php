<?php


namespace EasySwoole\EasySwoole;


use App\Task\AppWarningNotice;
use App\Task\AutoNotice;
use App\Task\CheckAccount;
use App\Task\CheckInstallApp;
use App\Task\CheckUdidToken;
use EasySwoole\Component\Timer;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\ORM\Db\Connection;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Config\RedisConfig;
use EasySwoole\RedisPool\RedisPool;

class EasySwooleEvent implements Event
{
    public static function initialize()
    {
        date_default_timezone_set('Asia/Shanghai');
        defined('ROOT_PATH') or define('ROOT_PATH', __DIR__ );
        $mc = Config::getInstance()->getConf("MYSQL");
        $mysqlConfig = new \EasySwoole\ORM\Db\Config();
        $mysqlConfig->setDatabase($mc["database"]);
        $mysqlConfig->setUser($mc["user"]);
        $mysqlConfig->setPassword($mc["password"]);
        $mysqlConfig->setHost($mc["host"]);

        //连接池配置
        $mysqlConfig->setGetObjectTimeout(3); //设置获取连接池对象超时时间
        $mysqlConfig->setIntervalCheckTime(30*1000); //设置检测连接存活执行回收和创建的周期
        $mysqlConfig->setMaxIdleTime(3); //连接池对象最大闲置时间(秒)
        $mysqlConfig->setMaxObjectNum($mc["POOL_MAX_NUM"]); //设置最大连接池存在连接对象数量
        $mysqlConfig->setMinObjectNum($mc["POOL_MIN_NUM"]); //设置最小连接池存在连接对象数量
        $mysqlConfig->setAutoPing(10); //设置自动ping客户端链接的间隔
        DbManager::getInstance()->addConnection(new Connection($mysqlConfig));

        $rc = Config::getInstance()->getConf("REDIS");
        $redisConfig = new RedisConfig();
        $redisConfig->setHost($rc["host"]);
        $redisConfig->setAuth($rc["auth"]);
        $redisConfig->setPort($rc["port"]);
        $redisPool = RedisPool::getInstance()->register($redisConfig);
        $redisPool->setMinObjectNum($rc["POOL_MIN_NUM"]);
        $redisPool->setMaxObjectNum($rc["POOL_MAX_NUM"]);
        $redisPool->setAutoPing(10);
    }

    public static function mainServerCreate(EventRegister $register)
    {
//        $register->add(EventRegister::onWorkerStart,function ($server,$workerId){
//            if($workerId==0){
//                Timer::getInstance()->loop(60*60*1000,function (){
//                    TaskManager::getInstance()->async(new CheckInstallApp());
//                });
//            }
//            if($workerId==2){
//                Timer::getInstance()->loop(10*60*1000,function (){
//                    TaskManager::getInstance()->async(new AutoNotice());
//                });
//            }
//            if($workerId==3){
//                Timer::getInstance()->loop(60*60*1000,function (){
//                    TaskManager::getInstance()->async(new AppWarningNotice());
//                });
//            }
//
//        });

    }
}