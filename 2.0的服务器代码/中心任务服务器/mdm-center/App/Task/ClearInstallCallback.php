<?php

namespace App\Task;

use App\Mode\App;
use App\Mode\AppInstallCallback;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class ClearInstallCallback implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {
        $id=0;
        while (true) {
            $list = null;
            $list = DbManager::getInstance()->invoke(function ($client) use ($id) {
                $data = App::invoke($client)
//                    ->where("is_download", 0)
                    ->where("is_abnormal", 0)
//                    ->where("is_resign", 0)
//                    ->where("status", 1)
//                    ->where("is_delete", 1)
//                    ->where("account_id", 62)
                    ->where("id", $id, '>')
                    ->order("id", "ASC")
                    ->limit(0, 50)
                    ->field("id,user_id,tag,is_abnormal,oss_path,is_resign,account_id,package_name,is_delete,is_download")
                    ->all();
                if ($data) {
                    return json_decode(json_encode($data), true);
                } else {
                    return null;
                }
            });
            if(empty($list)){
                break;
            }
            $time = date("Y-m-d H:i:s",strtotime("-1 months"));
            foreach ($list as $k => $v) {
                $id = $v["id"];
                DbManager::getInstance()->invoke(function ($client) use ($v,$time) {
                    AppInstallCallback::invoke($client)
                        ->where("app_id", $v["id"])
                        ->where("create_time",$time,"<")
                        ->destroy(null, true);
                });
                Logger::getInstance()->info("使用记录清除===".$v["id"]);
            }
            if (empty($list) || count($list) < 10) {
                break;
            }
        }
        Logger::getInstance()->info("使用记录清除完成");
        return true;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }


}