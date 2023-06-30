<?php


namespace App\Task;


use App\Mode\App;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class ProxyAsyncOss implements TaskInterface
{
    protected $taskData;

    public function __construct($data = [])
    {
        $this->taskData = $data;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        var_dump($throwable->getMessage());
    }

    public function run(int $taskId, int $workerIndex)
    {
        $data = $this->taskData;
        $proxy_id = $data["proxy_id"];
        $url = "http://8.218.63.79:85/index/proxy_tb_oss";
//        $url = "http://119.23.245.177:85/index/sign_resign_app";
        $id = 0;
        while (true) {
            $list = null;
            $list = DbManager::getInstance()->invoke(function ($client) use ($id, $proxy_id) {
                $data = App::invoke($client)
                    ->alias("a")
                    ->join("proxy_user u", "a.user_id=u.id", "LEFT")
                    ->where("u.pid", $proxy_id)
                    ->where("a.is_delete", 1)
                    ->where("a.is_download", 0)
                    ->where("a.id", $id, '>')
                    ->order("a.id", "ASC")
                    ->limit(0, 100)
                    ->field("a.id,a.name,a.oss_path")
                    ->all();
                if (!empty($data)) {
                    return json_decode(json_encode($data), true);
                } else {
                    return [];
                }
            });
            foreach ($list as $v) {
                $id = $v["id"];
                $post = [
                    "oss_path" => $v["oss_path"],
                    "sign" => strtoupper(md5($v["oss_path"] . "kiopmwhyusn")),
                    "async_oss_config" => $data["async_oss_config"]
                ];
                $result = $this->http_client($url, $post);
                Logger::getInstance()->info("代理批量同步分流库 同步结果： === " . $v["id"] . " == " . $result->getBody());
            }
            if(empty($list)||count($list)<50){
                break;
            }
            \co::sleep(3);
        }
        return true;
    }

    protected function http_client($url, $data = [], $header = [])
    {
        $client = new HttpClient();
        $client->setUrl($url);
        $client->setTimeout(-1);
        if (!empty($header)) {
            $client->setHeaders($header);
        }
        if (!empty($data)) {
            $result = $client->post($data);
        } else {
            $result = $client->get();
        }
        return $result;
    }

}