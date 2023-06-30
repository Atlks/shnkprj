<?php


namespace App\Task;


use App\Lib\Push;
use App\Mode\UdidToken;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use GuzzleHttp\Client;

class DownloadPush implements TaskInterface
{

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
        Logger::getInstance()->error("推送错误:" . $throwable->getMessage());
    }

    public function run(int $taskId, int $workerIndex)
    {

//        $length =  RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) {
//            $redis->select(6);
//            return  $redis->lLen("download_push");
//        });

        $redis = RedisPool::defer();
        $redis->select(6);
        $length = $redis->lLen("download_push");
        if($length<=0){
            return  true;
        }
//        $push_m1 = new Push(EASYSWOOLE_ROOT . "/extend/push/m1/m1.pem");
//        $push_m2 = new Push(EASYSWOOLE_ROOT . "/extend/push/m2/push.pem");
        $push_m3 = new Push(EASYSWOOLE_ROOT . "/extend/push/m3/push.pem");
//        $push_m4 = new Push(EASYSWOOLE_ROOT . "/extend/push/m4/push.pem");
        for ($i = 0; $i < $length; $i++) {
//            $val =  RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) {
//                $redis->select(6);
//               return $redis->lPop("download_push");
//            });
            $val = $redis->lPop("download_push");
            if ($val) {
                $data = json_decode($val, true);
                if(!is_array($data)||!isset($data["udid"])||empty($data["udid"])){
                    Logger::getInstance()->error("推送数据错误： ".$val);
                    continue;
                }
                if(empty($data["udid_token"])){
                    $udid = $data["udid"];
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
//                if( $data["udid"]=="be19f0577470e0ff5f7819908be04282a093b8af"){
//                    $pem = EASYSWOOLE_ROOT . "/extend/push/m2/push.pem";
//                    $result = $this->push_client($data["udid_token"], $data["push_magic"], $data["topic"], $pem);
//                    if ($result === false) {
//                        Logger::getInstance()->error("CLIENT 推送失败： " . $data["udid"]);
//                    }
//                    return true;
//                }
//                if ($data["topic"] == "com.apple.mgmt.External.117afaf2-ca3d-44d6-b8ff-4c1c30d40458") {
//                    $pem = EASYSWOOLE_ROOT . "/extend/push/m1/m1.pem";
//                }elseif ($data["topic"] == "com.apple.mgmt.External.88a08591-083a-4c02-b3a2-78aef5b67371"){
//                    $pem = EASYSWOOLE_ROOT . "/extend/push/m3/push.pem";
//                }elseif ($data['topic']=="com.apple.mgmt.External.f8c97b0a-9368-41bf-a174-bd15c1a8adf0"){
//                    $pem = EASYSWOOLE_ROOT . "/extend/push/m4/push.pem";
//                }else{
//                    $pem = EASYSWOOLE_ROOT . "/extend/push/m2/push.pem";
//                }
//                $result = $this->push_client($data["udid_token"], $data["push_magic"], $data["topic"], $pem);
//                if($result !==true){
//                    $result = $this->push_client($data["udid_token"], $data["push_magic"], $data["topic"], $pem);
//                }
                if ($data["topic"] == "com.apple.mgmt.External.117afaf2-ca3d-44d6-b8ff-4c1c30d40458") {
                    continue;
                   $result =  $push_m1->push_redis_message($data["udid_token"], $data["push_magic"]);
                    if($result===false){
                        $result =  $push_m1->push_redis_message($data["udid_token"], $data["push_magic"]);
                    }
                } elseif ($data["topic"] == "com.apple.mgmt.External.88a08591-083a-4c02-b3a2-78aef5b67371") {
                    $result =  $push_m3->push_redis_message($data["udid_token"], $data["push_magic"]);
                    if($result===false){
                        $result =  $push_m3->push_redis_message($data["udid_token"], $data["push_magic"]);
                    }
                } elseif ($data['topic']=="com.apple.mgmt.External.f8c97b0a-9368-41bf-a174-bd15c1a8adf0"){
                    continue;
                    $result =  $push_m4->push_redis_message($data["udid_token"], $data["push_magic"]);
                    if($result===false){
                        $result =  $push_m4->push_redis_message($data["udid_token"], $data["push_magic"]);
                    }
                }else {
                    continue;
                    $result = $push_m2->push_redis_message($data["udid_token"], $data["push_magic"]);
                    if ($result === false) {
                        $result = $push_m2->push_redis_message($data["udid_token"], $data["push_magic"]);
                    }
                }
                if ($result === false) {
                    Logger::getInstance()->error("推送失败： " . $data["udid"]);
                }
            }
        }
//        $push_m1->close_push_ssl("m1-".$taskId."-".$workerIndex);
//        $push_m2->close_push_ssl("m2-".$taskId."-".$workerIndex);
        $push_m3->close_push_ssl("m3-".$taskId."-".$workerIndex);
//        $push_m4->close_push_ssl("m4-".$taskId."-".$workerIndex);
        return true;
    }


    /**
     * 新版推送
     * @param $token
     * @param $mdm
     * @param $topic
     * @param $cert_path
     * @return bool
     */
    public function push_client($token,$mdm,$topic,$cert_path){
        $push_url = 'https://api.push.apple.com:2197/3/device/'.bin2hex(base64_decode($token));
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
        $client = new Client();
        try {
            $result = $client->postAsync($push_url,[
                "headers"=>$header,
                "cert"=>$cert_path,
                "body"=>json_encode($data),
                'curl' => [CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_2_0],
                "verify"=>false
            ]);
            $status =  $result->getState();
            if($status=="pending"){
                return true;
            }else{
                return false;
            }
        }catch (\Throwable $exception){
            var_dump($exception->getMessage());
            return false;
        }
    }

}