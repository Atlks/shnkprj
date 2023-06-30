<?php

namespace App\Task;

use EasySwoole\EasySwoole\Logger;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use GuzzleHttp\Client;

class AdminPush implements TaskInterface
{

    protected  $client;


    public function __construct()
    {
        $this->client = new Client();
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }


    public function run(int $taskId, int $workerIndex)
    {
        $error_num = 0;
        while (true){
            try {
                $data =   RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis){
                    $redis->select(2);
                    return $redis->lPop("push_list_task");
                });
                if(empty($data)){
                    break;
                }
                $data = json_decode($data,true);
                if ($data["topic"]=="com.apple.mgmt.External.179a2164-6c2e-4e9b-9b90-14c1ff97d269"){
                    $pem =  EASYSWOOLE_ROOT . "/extend/client/push/m5/push.pem";
                }elseif ($data["topic"]=="com.apple.mgmt.External.3e2873ec-5182-4a6d-b842-ad4427424c37"){
                    $pem =EASYSWOOLE_ROOT . "/extend/client/push/m6/push.pem";
                }else{
                    continue;
                }
                $package_name = $data["package_name"];
                foreach ($data["data"] as $v){
                    RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($v,$package_name) {
                        $redis->select(1);
                        $task_data = [
                            "udid" => $v["udid"],
                            "app_token" => $v["app_token"],
                            "app_id" => $v["app_id"]
                        ];
                        $redis->set("is_admin_push:" .  $v["udid"],$package_name,600);
                        $redis->set("task:" . $v["udid"],json_encode($task_data),600);
                    });
                    $result = $this->push_client($v["udid_token"], $v["push_magic"], $v["topic"], $pem);
                }
            }catch (\Throwable $throwable){
                var_dump($throwable->getMessage());
                $error_num++;
                if($error_num>10){
                    break;
                }else{
                    continue;
                }
            }
        }
    }

    /**
     * 新版推送
     * @param $token
     * @param $mdm
     * @param $topic
     * @param $cert_path
     * @param $udid
     * @return bool
     */
    public function push_client($token,$mdm,$topic,$cert_path,$udid=""){
        $query_params = bin2hex(base64_decode($token));
        if(empty($query_params)){
            return true;
        }
//        $push_url = 'https://api.push.apple.com:2197/3/device/'.$query_params;
        $push_url = 'https://api.push.apple.com:443/3/device/'.$query_params;
        $header=[
            "apns-topic"=>$topic,
            "apns-push-type"=>"mdm",
            "apns-expiration"=>0,
            "apns-priority"=>10,
        ];
        $data=[
            "aps" => [
                "sound" => "default.caf",
            ],
            "mdm"=>$mdm
        ];
        try {
            $result = $this->client->post($push_url,[
                "headers"=>$header,
                "cert"=>$cert_path,
                "body"=>json_encode($data),
                'curl' => [CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_2_0],
                "verify"=>false,
                "timeout"=>20
            ]);
            $status =  $result->getStatusCode();
            if($status==200){
                return true;
            }elseif ($status==410){
                return false;
            }else{
                return false;
            }
        }catch (\Throwable $exception){
            Logger::getInstance()->error($exception->getMessage());
//            var_dump($exception->getMessage());
            return false;
        }
    }

}