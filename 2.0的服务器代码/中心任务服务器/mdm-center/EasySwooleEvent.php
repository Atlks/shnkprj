<?php


namespace EasySwoole\EasySwoole;


use App\Task\ApkDownloadCheck;
use App\Task\AppAutoStart;
use App\Task\AppDownloadCheck;
use App\Task\AppViewList;
use App\Task\AppWarningNotice;
use App\Task\AutoNotice;
use App\Task\AutoOssToGoogle;
use App\Task\AutoRefush;
use App\Task\CheckAccount;
use App\Task\CheckHostStatus;
use App\Task\CheckInstallApp;
use App\Task\CheckInstallIdfv;
use App\Task\CheckUdidToken;
use App\Task\ClearInstallAppCall;
use App\Task\InstallUdidPush;
use App\Task\AlibSignBatchClient;
use App\Task\XingkongSignBatchClient;
use App\Task\ResignFlow;
use EasySwoole\Component\Timer;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\ORM\Db\Connection;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Config\RedisConfig;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Spl\SplBean;

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
        $register->add(EventRegister::onWorkerStart,function ($server,$workId){
            if ($workId == 0) {
                Timer::getInstance()->loop(1000, function () {
                    TaskManager::getInstance()->async(new ResignFlow());
                });
            }
            if($workId==1){
                /***每隔3s检查一次token**/
                Timer::getInstance()->loop(2000,function (){
                    TaskManager::getInstance()->async(new CheckUdidToken());
                });
            }

            if($workId==5){
//                /***主动推送**/
//                Timer::getInstance()->loop(500,function (){
//                    TaskManager::getInstance()->async(new InstallUdidPush());
//                });

                /***主动提交linux签名任务**/
                Timer::getInstance()->loop(600,function (){
                    TaskManager::getInstance()->async(new AlibSignBatchClient());
                    TaskManager::getInstance()->async(new XingkongSignBatchClient());
                });   
                /**域名检测**/
                Timer::getInstance()->loop(10*60*1000,function (){
                    TaskManager::getInstance()->async(new CheckHostStatus());
                });
            }

            if($workId==7){
                /***主动提交linux签名任务**/
                Timer::getInstance()->loop(500,function (){
                    TaskManager::getInstance()->async(new AlibSignBatchClient());
                    TaskManager::getInstance()->async(new XingkongSignBatchClient());
                });   
            }

            if($workId==3){
                Timer::getInstance()->loop(30*1000,function (){
                    TaskManager::getInstance()->async(new AutoOssToGoogle());
                });
            }
            if($workId==2){
                Timer::getInstance()->loop(30*60*1000,function (){
                    TaskManager::getInstance()->async(new ClearInstallAppCall());
                });
                /***异常统计**/
                Timer::getInstance()->loop(60*60*1000,function (){
                    TaskManager::getInstance()->async(new CheckInstallApp());
                });

            }
            if($workId==6){
                Timer::getInstance()->loop(500, function () {
                    $redis = RedisPool::defer();
                    $redis->select(8);
                    $length = $redis->lLen("auto_add");
                    for ($i = 0; $i < $length; $i++) {
                        $val = $redis->lPop("auto_add");
                        if ($val) {
                            TaskManager::getInstance()->async(new AutoRefush(["app_id"=>$val]));
                        } else {
                            break;
                        }
                    }
                });
                /**异常提醒**/
                Timer::getInstance()->loop(20*60*1000,function (){
                    TaskManager::getInstance()->async(new AppAutoStart());
                });
            }

            if($workId == 8){
                /***每隔一小时检查一次账号**/
                Timer::getInstance()->loop(30*60*1000,function (){
                    TaskManager::getInstance()->async(new ApkDownloadCheck());
                });
                /***每隔一小时检查一次账号**/
                Timer::getInstance()->loop(70*60*1000,function (){
                    TaskManager::getInstance()->async(new CheckInstallIdfv());
                });
            }


            if($workId ==10){
                /***每隔一小时检查一次账号**/
                Timer::getInstance()->loop(30*60*1000,function (){
                    TaskManager::getInstance()->async(new CheckAccount());
                });
                /**剩余次数统计**/
                Timer::getInstance()->loop(10*60*1000,function (){
                    TaskManager::getInstance()->async(new AutoNotice());
                });
                /**异常提醒**/
                Timer::getInstance()->loop(60*60*1000,function (){
                    TaskManager::getInstance()->async(new AppWarningNotice());
                });
            }
        });

    }
}