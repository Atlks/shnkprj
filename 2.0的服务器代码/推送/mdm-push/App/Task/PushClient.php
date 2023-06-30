<?php


namespace App\Task;

use App\Lib\Push;
use App\Mode\UdidToken;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use GuzzleHttp\Client;

class PushClient implements TaskInterface
{

    protected  $client;


    public function __construct()
    {
        $this->client = new Client();
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
        Logger::getInstance()->error("Client 推送错误:" . $throwable->getMessage());
    }

    public function run(int $taskId, int $workerIndex)
    {

        while (true){
            $val =  RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) {
                $redis->select(6);
               return $redis->lPop("download_push");
            });
            if ($val) {
                $data=null;
                $data = json_decode($val, true);
                if(!is_array($data)||!isset($data["udid"])||empty($data["udid"])){
                    Logger::getInstance()->error("推送数据错误： ".$val);
                    continue;
                }
                $udid = $data["udid"];
                if(empty($data["udid_token"])){
                    $data = DbManager::getInstance()->invoke(function ($client) use ($udid) {
                        $data = UdidToken::invoke($client)->where("udid", $udid)->get();
                        if (!empty($data)) {
                            return $data->toArray();
                        } else {
                            return null;
                        }
                    });
                    if(empty($data)){
                        Logger::getInstance()->error("推送数据错误==未查询到数据： ".$val);
                        continue;
                    }
                }
                if ($data["topic"]=="com.apple.mgmt.External.179a2164-6c2e-4e9b-9b90-14c1ff97d269"){
                    $pem = EASYSWOOLE_ROOT . "/extend/client/push/m5/push.pem";
                }elseif ($data["topic"]=="com.apple.mgmt.External.3e2873ec-5182-4a6d-b842-ad4427424c37"){
                    $pem = EASYSWOOLE_ROOT . "/extend/client/push/m6/push.pem";
                }else{
                    DbManager::getInstance()->invoke(function ($client) use ($udid) {
                        UdidToken::invoke($client)->where('udid', $udid)->update(['is_delete' => 0, "check_status" => 1, "delete_time" => date("Y-m-d H:i:s")]);
                    });
                    RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($udid) {
                        $redis->select(2);
                        return  $redis->del("udidToken:" . $udid);
                    });
                    continue;
                }
//                if ($data["topic"] == "com.apple.mgmt.External.117afaf2-ca3d-44d6-b8ff-4c1c30d40458") {
//                    $pem = EASYSWOOLE_ROOT . "/extend/client/push/m1/m1.pem";
//                    continue;
//                }elseif ($data["topic"] == "com.apple.mgmt.External.88a08591-083a-4c02-b3a2-78aef5b67371"){
//                    $pem = EASYSWOOLE_ROOT . "/extend/client/push/m3/push.pem";
//                    continue;
//                }elseif ($data['topic']=="com.apple.mgmt.External.f8c97b0a-9368-41bf-a174-bd15c1a8adf0"){
//                    $pem = EASYSWOOLE_ROOT . "/extend/client/push/m4/push.pem";
//                    continue;
//                }elseif ($data["topic"]=="com.apple.mgmt.External.179a2164-6c2e-4e9b-9b90-14c1ff97d269"){
//                    $pem = EASYSWOOLE_ROOT . "/extend/client/push/m5/push.pem";
//                }else{
//                    $pem = EASYSWOOLE_ROOT . "/extend/client/push/m2/push.pem";
//                    continue;
//                }
                if(empty($data["udid_token"])){
                    continue;
                }
                $result = $this->push_client($data["udid_token"], $data["push_magic"], $data["topic"], $pem);
                if($result !==true){
                    $result = $this->push_client($data["udid_token"], $data["push_magic"], $data["topic"], $pem);
                }
                if ($result === false) {
                    Logger::getInstance()->error("CLIENT 推送失败： " . $data["udid"]);
//                    RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ( $data){
//                        $redis->select(12);
//                        return $redis->set("push:". $data["udid"],"CLIENT 推送失败",1800);
//                    });
                }
            }else{
              break;
            }
        }
        return true;
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
                "timeout"=>10
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