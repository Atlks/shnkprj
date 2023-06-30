<?php


namespace App\Task;


use App\Model\App;
use App\Model\AppInstallCallback;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class AppWarningNotice implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {
        $list = DbManager::getInstance()->invoke(function ($client) {
            return  App::invoke($client)
                ->where("status",1)
                ->where("is_delete",1)
                ->where("is_abnormal",1)
                ->where("is_download",0)
                ->where("is_resign",0)
                ->where("abnormal_num",5,">=")
                ->field("id,name,abnormal_num")
                ->all();
        });
        if(empty($list)){
            return true;
        }
        $list = json_decode(json_encode($list), true);
        $url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
        foreach ($list as $k=>$v){
            $post_data = [
                "chat_id" => "-1001463689548",
                "text" => "2.0 APP下载异常提醒：APP< " . $v["name"] . " > APPID < " . $v["id"]. " >异常次数：" . $v["abnormal_num"],
            ];
            $result = $this->http_client($url, $post_data);
            /**异常超过100 开启重签**/
            if($v["abnormal_num"]>=5){
//                DbManager::getInstance()->invoke(function ($client) use ($v) {
//                    AppInstallCallback::invoke($client)
//                        ->where("app_id", $v["id"])
//                        ->destroy(null, true);
//                });
                /***异常自动开启重签**/
                $post = [
                    'app_id' => $v['id'],
                    "key"=>md5($v['id']."iossign"),
                    "is_resign"=>1
                ];
                $async = $this->http_client("http://47.242.10.67:85/index/alib_app", $post);
            }
        }
        Logger::getInstance()->info("APP异常下载检测===");
        return true;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
        var_dump($throwable->getMessage());
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
            $client->setContentTypeFormData();
            $result = $client->post($data);
        } else {
            $result = $client->get();
        }
        return $result;
    }

}