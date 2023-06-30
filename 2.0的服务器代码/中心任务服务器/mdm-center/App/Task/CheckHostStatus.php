<?php


namespace App\Task;


use App\Mode\CheckHost;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class CheckHostStatus implements TaskInterface
{
    protected $client;

    public function __construct()
    {
        $this->client = new HttpClient();

    }


    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
        Logger::getInstance()->error("域名检测错误:" . $throwable->getMessage());
    }

    public function run(int $taskId, int $workerIndex)
    {
        $list = DbManager::getInstance()->invoke(function ($client) {
            return  CheckHost::invoke($client)
                ->where("status",1)
                ->all();
        });
        if(empty($list)){
            return true;
        }
        $list = json_decode(json_encode($list), true);
        $url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
        $api_url = "http://8.134.90.62/check.php?host=";
        $send_client = new HttpClient($url);

        foreach ($list as $v){
            $result = null;
            $notice = null;
            $result = $this->http_client($api_url.$v["host"]);
            if(empty($result)){
                continue;
            }
            $re = json_decode($result,true);
            if(isset($re["code"])){
                /**域名无法请求通过**/
                if($re["code"]==100){
                    $update=[
                        "status"=>0,
                        "revoke_time"=>date("Y-m-d H:i:s")
                    ];
                    DbManager::getInstance()->invoke(function ($client) use ($v, $update) {
                        CheckHost::invoke($client)->where('id', $v["id"])->update($update);
                    });
                    $post_data = [
                        "chat_id" => "-1001463689548",
                        "text" => "域名备案已掉或无法访问，失效域名： ".$v["host"]." ,请及时查看或更换域名",
                    ];
                    $notice = $this->send_message($send_client, $post_data);
                }
            }
        }
        return  true;
    }

   public function http_client( $url, $data = [], $header = [])
    {
//        $client = new HttpClient();
        $this->client->setUrl($url);
        $this->client->setTimeout(50);
        if (!empty($header)) {
            $this->client->setHeaders($header);
        }
        if (!empty($data)) {
            $result = $this->client->post($data);
        } else {
            $result = $this->client->get();
        }
        return  $result->getBody();
    }

    public function send_message($send_client, $data = []){
//        $client = new HttpClient($url);
        $send_client->setTimeout(-1);
        if (!empty($header)) {
            $send_client->setHeaders($header);
        }
        if (!empty($data)) {
            $result = $send_client->post($data);
        } else {
            $result = $send_client->get();
        }
        return  $result->getStatusCode();
    }


}