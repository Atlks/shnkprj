<?php


namespace App\Task;


use App\Lib\RedisLib;
use App\Mode\UdidToken;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class ClearUdidToken implements TaskInterface
{
    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    public function run(int $taskId, int $workerIndex)
    {
        $id=0;
        while (true) {
            Logger::getInstance()->info("删除无效UDID ====$id===");
            $list = null;
            $list = DbManager::getInstance()->invoke(function ($client) use ($id) {
                $data = UdidToken::invoke($client)
                    ->where("id", $id, ">")
                    ->order("id", "ASC")
                    ->limit(0, 500)
                    ->field("id,udid,topic,is_delete")
                    ->all();
                if ($data) {
                    return json_decode(json_encode($data), true);
                } else {
                    return null;
                }
            });
            if (empty($list)) {
                break;
            }
            foreach ($list as $v) {
                $id = $v["id"];
                if ($v["topic"] != "com.apple.mgmt.External.179a2164-6c2e-4e9b-9b90-14c1ff97d269" || $v["topic"] != "com.apple.mgmt.External.88a08591-083a-4c02-b3a2-78aef5b67371"|| $v["topic"] != "com.apple.mgmt.External.3e2873ec-5182-4a6d-b842-ad4427424c37") {
                    $udid = $v["udid"];
                    DbManager::getInstance()->invoke(function ($client) use ($udid) {
                        UdidToken::invoke($client)->where('udid', $udid)->update(['is_delete' => 0, "delete_time" => date("Y-m-d H:i:s")]);
                    });
                    RedisLib::del("udidToken:" . $udid, 2);
                }
            }
            if(count($list)<200){
                break;
            }
        }
        Logger::getInstance()->info("删除无效UDID完成 ====$id===");
        return true;
    }

}