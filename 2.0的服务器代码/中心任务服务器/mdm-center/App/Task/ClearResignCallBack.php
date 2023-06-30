<?php


namespace App\Task;


use App\Lib\RedisLib;
use App\Mode\App;
use App\Mode\AppInstallCallback;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class ClearResignCallBack implements TaskInterface
{
    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    public function run(int $taskId, int $workerIndex)
    {
        $list = DbManager::getInstance()->invoke(function ($client) {
            return  App::invoke($client)
                ->where("status",1)
                ->where("is_delete",1)
                ->where("is_download",0)
                ->where("is_resign", 1)
                ->where("account_id", 61)
//                ->where("is_abnormal",1)
                ->field("id,name,abnormal_num")
                ->all();
        });
        if(empty($list)){
            return true;
        }
        $list = json_decode(json_encode($list), true);
        foreach ($list as $k=>$v){
                DbManager::getInstance()->invoke(function ($client) use ($v) {
                    AppInstallCallback::invoke($client)
                        ->where("app_id", $v["id"])
                        ->destroy(null, true);
                });
            Logger::getInstance()->info("重签使用记录清除===".$v["id"]);
        }
        RedisLib::del("appInstall_udid",5);
        Logger::getInstance()->info("重签使用记录清除全部完成===");
        return true;
    }

}