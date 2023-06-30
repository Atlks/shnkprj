<?php


namespace App\Task;


use App\Mode\App;
use App\Mode\ProxyAppApkDownloadLog;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class ApkDownloadCheck implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {

        $time = date("Y-m-d");
        $id = 0;
        while (true) {
            $list = null;
            $list = DbManager::getInstance()->invoke(function ($client) use($id) {
                return App::invoke($client)
                    ->where("status", 1)
                    ->where("is_delete", 1)
                    ->where("is_download", 0)
                    ->where("id", $id, '>')
                    ->order("id", "ASC")
                    ->limit(0, 500)
                    ->field("id,name,apk_url,status,is_delete,is_download,apk_num,apk_today_num")
                    ->all();
            });
            if (empty($list)) {
               break;
            }
            $list = json_decode(json_encode($list), true);
            foreach ($list as $k => $v) {
                $id = $v["id"];
                if ($v["is_download"] == 1 || $v["status"] == 0) {

                    continue;
                }
                if (empty($v["apk_url"])) {
                    continue;
                }
                $total = DbManager::getInstance()->invoke(function ($client) use ($v) {
                    return ProxyAppApkDownloadLog::invoke($client)->where("app_id", $v["id"])
                        ->count("id");
                });
                $today_num = DbManager::getInstance()->invoke(function ($client) use ($v, $time) {
                    return ProxyAppApkDownloadLog::invoke($client)->where("app_id", $v["id"])
                        ->where("create_time", $time . "  00:00:00", ">")
                        ->count("id");
                });
                $update = [
                    "apk_num" => $total,
                    "apk_today_num" => $today_num,
                ];
                DbManager::getInstance()->invoke(function ($client) use ($v, $update) {
                    App::invoke($client)->where("id", $v["id"])
                        ->update($update);
                });
            }
            if (empty($list) || count($list) < 300) {
                break;
            }
        }
        Logger::getInstance()->info("安卓下载检测完成===");
        return true;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
        var_dump($throwable->getMessage());
    }

    function http_client( $url, $data = [], $header = [])
    {
        $client = new HttpClient($url);
//        $client->setUrl($url);
        $client->setTimeout(-1);
        if (!empty($header)) {
            $client->setHeaders($header);
        }
        if (!empty($data)) {
//            $client->setContentTypeFormData();
            $result = $client->post($data);
        } else {
            $result = $client->get();
        }
        return  $result->getStatusCode();
    }

}