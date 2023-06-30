<?php


namespace App\Task;


use App\Lib\GoogleOss;
use App\Lib\Oss;
use App\Mode\App;
use App\Mode\Enterprise;
use co;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use Throwable;

class AsyncOss implements TaskInterface
{

    protected $max_id;

    protected $init_id;

    public function __construct($taskData)
    {
        $this->max_id = $taskData["max_id"];
        $this->init_id = $taskData["id"];
    }

    public function onException(Throwable $throwable, int $taskId, int $workerIndex)
    {
        var_dump($throwable->getMessage());
    }

    public function run(int $taskId, int $workerIndex)
    {
        $url = "http://34.92.69.39/index/google_to_google";
        $id = $this->init_id;
        $num = 0;
        while (true) {
            $list = DbManager::getInstance()->invoke(function ($client) use ($id) {
                $data = App::invoke($client)
                    ->where("status", 1)
                    ->where("is_delete", 1)
                    ->where("is_download", 0)
                    ->where("id", $id, '>')
                    ->order("id", "ASC")
                    ->limit(0, 50)
                    ->field("id,name,oss_path,apk_url")
                    ->all();
                if (!empty($data)) {
                    return json_decode(json_encode($data), true);
                } else {
                    return [];
                }
            });
            if(empty($list)){
                break;
            }
            foreach ($list as $v) {
                $id = $v["id"];
                $num++;
                $google_private_oss_config = Config::getInstance()->getConf("Google_NEW_PRIVATE_OSS");
                $google_private = new GoogleOss($google_private_oss_config);
                if(!$google_private->exists($v["oss_path"])){
                    $post = [
                        "oss_path" => $v["oss_path"],
                        "sign" => strtoupper(md5($v["oss_path"] . "kiopmwhyusn"))
                    ];
                    $result = $this->http_client($url, $post);
                    Logger::getInstance()->info("google 同步结果：$num === " . $v["id"] . " == " . $result->getBody());
                }

                if (!empty($v["apk_url"]) && !strstr($v["apk_url"], 'http')) {
                    if(!$google_private->exists($v["apk_url"])) {
                        $post_apk = [
                            "oss_path" => $v["apk_url"],
                            "sign" => strtoupper(md5($v["apk_url"] . "kiopmwhyusn"))
                        ];
                        $result = $this->http_client($url, $post_apk);
                        Logger::getInstance()->info("google 同步结果：$num === " . $v["id"] . " == " . $result->getBody());
                    }
                }
            }
            if (empty($list) || count($list) < 10) {
                break;
            }
            if ($id >= $this->max_id) {
                break;
            }
            co::sleep(5);
        }
        return true;
    }

    public function http_client($url, $data = [], $header = [])
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