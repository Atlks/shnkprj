<?php


namespace App\Task;


use App\Mode\AppInstallCallback;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class ClearInstallAppCall implements TaskInterface
{
    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    public function run(int $taskId, int $workerIndex)
    {
        $time = date("Y-m-d")." 00:00:00";
        $id = 1056082;

        while (true) {
            $list = DbManager::getInstance()->invoke(function ($client) use ($id, $time) {
                return AppInstallCallback::invoke($client)
                    ->where("id", $id, ">")
                    ->where("create_time", $time, ">")
                    ->limit(0,500)
                    ->all();
            });
            if(empty($list)){
                break;
            }
            $list = array_values($list);
            foreach ($list as $k => $v) {
                $id = $v["id"];
                if(!isset($list[$k+1])){
                    continue;
                }
                $cache_v = $list[$k + 1];
                if (!empty($cache_v)) {
                    if ($v["app_id"] == $cache_v["app_id"] && $v["idfv"] == $cache_v["idfv"] && $v["device"] == $cache_v["device"] && $v["osversion"] == $cache_v["osversion"] && $v["create_time"] == $cache_v["create_time"] && $v["user_id"] == $cache_v["user_id"] && $v["ip"] == $cache_v["ip"]) {
                    DbManager::getInstance()->invoke(function ($client) use ($v) {
                        AppInstallCallback::invoke($client)
                            ->where("id", $v["id"])
                            ->where("app_id", $v["app_id"])
                            ->destroy(null, true);
                    });
                        Logger::getInstance()->info("数据重复：   " . $v["id"] . "===" . $cache_v["id"]);
                    }
                } else {
                    continue;
                }
            }
            if(count($list)<100){
                break;
            }
        }
        $this->http_client("http://47.243.64.78:85/api/check_install_app");
        Logger::getInstance()->info("数据重复：  删除完成 " );
        return true;
    }

    function http_client($url, $data = [], $header = [])
    {
        $client = new HttpClient();
        $client->setUrl($url);
        $client->setTimeout(60);
        if (!empty($header)) {
            $client->setHeaders($header);
        }
        if (!empty($data)) {
//            $client->setContentTypeFormData();
            $result = $client->post($data);
        } else {
            $result = $client->get();
        }
        return $result;
    }

}