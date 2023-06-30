<?php


namespace App\Task;


use App\Mode\ProxyAppViews;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class AppViewList implements TaskInterface
{
    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        Logger::getInstance()->error("APPviews:".$throwable->getMessage());
    }

    public function run(int $taskId, int $workerIndex)
    {
        while (true){
            $redis = RedisPool::defer();
            $redis->select(8);
            $data = $redis->lPop("app_views");
            if(!empty($data)){
                $view_data = json_decode($data,true);
                DbManager::getInstance()->invoke(function ($client)use($view_data){
                    ProxyAppViews::invoke($client)->tableName($view_data["table"])
                        ->data($view_data["data"],false)->save();
                });
                $data = null;
            }else{
                break;
            }
        }
        return true;
    }

}