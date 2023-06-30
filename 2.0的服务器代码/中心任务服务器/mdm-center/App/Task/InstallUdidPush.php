<?php


namespace App\Task;


use App\Lib\Push;
use App\Lib\RedisLib;
use App\Mode\UdidToken;
use EasySwoole\ORM\DbManager;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use GuzzleHttp\Client;

/**安装MDM推送**/
class InstallUdidPush implements TaskInterface
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client(["base_uri"=>"https://api.push.apple.com"]);
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    public function run(int $taskId, int $workerIndex)
    {
        $redis = RedisPool::defer();
        $redis->select(11);
        $length = $redis->lLen("install_init_udid");
        for ($i = 0; $i < $length; $i++) {
            $val = $redis->lPop("install_init_udid");
            if ($val) {
                $udid = $val;
                $udid_token = RedisLib::hGetAll("udidToken:".$udid,2);
                if(empty($udid_token)) {
                    $udid_token = DbManager::getInstance()->invoke(function ($client) use ($udid) {
                        $data = UdidToken::invoke($client)->where("udid", $udid)->get();
                        if (!empty($data)) {
                            return $data->toArray();
                        } else {
                            return null;
                        }
                    });
                    RedisLib::hMSet("udidToken:".$udid,$udid_token,2);
                }
                if(empty($udid_token)){
                    continue;
                }
                if(empty($udid_token["udid_token"])){
                    continue;
                }
                if  ($udid_token["topic"] == "com.apple.mgmt.External.88a08591-083a-4c02-b3a2-78aef5b67371"){
                    $pem = EASYSWOOLE_ROOT . "/extend/push/m3/push.pem";
                }elseif ($udid_token["topic"]=="com.apple.mgmt.External.179a2164-6c2e-4e9b-9b90-14c1ff97d269"){
                    $pem = EASYSWOOLE_ROOT . "/extend/push/m5/push.pem";
                }elseif ($udid_token["topic"]=="com.apple.mgmt.External.3e2873ec-5182-4a6d-b842-ad4427424c37"){
                    $pem = EASYSWOOLE_ROOT . "/extend/push/m6/push.pem";
                }else{
                    continue;
                }

                $result = $this->push_client($udid_token["udid_token"], $udid_token["push_magic"], $udid_token["topic"], $pem);
                if($result !==true){
                    $result = $this->push_client($udid_token["udid_token"], $udid_token["push_magic"], $udid_token["topic"], $pem,true);
                }
//                /**
//                 * @todo 推送证书
//                 */
//                if ($udid_token["topic"] == "com.apple.mgmt.External.117afaf2-ca3d-44d6-b8ff-4c1c30d40458") {
//                    $PEM = EASYSWOOLE_ROOT . "/extend/push/m1/m1.pem";
//                } elseif ($udid_token["topic"] == "com.apple.mgmt.External.88a08591-083a-4c02-b3a2-78aef5b67371") {
//                    $PEM = EASYSWOOLE_ROOT . "/extend/push/m3/push.pem";
//                } elseif ($udid_token['topic']=="com.apple.mgmt.External.f8c97b0a-9368-41bf-a174-bd15c1a8adf0"){
//                    $PEM = EASYSWOOLE_ROOT . "/extend/push/m4/push.pem";
//                }else {
//                    $PEM = EASYSWOOLE_ROOT . "/extend/push/m2/push.pem";
//                }
//                $push = new Push($PEM);
//                $push->startMdm($udid_token["udid_token"], $udid_token["push_magic"]);
//                $push = null;
            } else {
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
     * @param bool $debug
     * @return bool
     */
    public function push_client($token,$mdm,$topic,$cert_path,$debug=false){
        $query_params = bin2hex(base64_decode($token));
        if(empty($query_params)){
            return true;
        }
//        $push_url = 'https://api.push.apple.com:2197/3/device/'.$query_params;
        $push_url = '/3/device/'.$query_params;
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
                "timeout"=>30,
                "debug"=>$debug,
                'http_errors' => false
            ]);
            $status =  $result->getStatusCode();
            if($status==200){
                return true;
            }elseif ($status==410){
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