<?php


namespace App\Task;


use App\Mode\App;
use App\Mode\AppResignInstallCallback;
use App\Mode\Enterprise;
use App\Mode\ResignSignStr;
use App\Mode\SiteConfig;
use co;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use Throwable;

class ResignFlow implements TaskInterface
{
    protected $taskData;

    public function __construct($data = [])
    {
        $this->taskData = $data;
    }

    public function onException(Throwable $throwable, int $taskId, int $workerIndex)
    {
        Logger::getInstance()->error("任务分发错误:" . $throwable->getMessage());
    }

    public function run(int $taskId, int $workerIndex)
    {
        $redis = RedisPool::defer();
        $redis->select(8);
        $length = $redis->lLen("task");
        for ($i = 0; $i < $length; $i++) {
            $val = $redis->lPop("task");
            if ($val) {
                $data = json_decode($val, true);
//                $data = $this->taskData;
//                if ($data["is_mac"] == 1) {
//                    if ($data['is_overseas'] == 10) {
//                        $resign_url = "http://207.254.38.251:85/index/sign_resign_app";
//                    } else {
//                        $resign_url = "http://207.254.52.185:85/index/sign_resign_app";
//                    }
//                } else {
                    if ($data['is_overseas'] == 10) {
                        $resign_url = "http://119.23.245.177:85/index/sign_resign_app";
                    } else {
                        $resign_url = "http://8.218.75.38:85/index/sign_resign_app";
//                        if($data["udid"]=="be19f0577470e0ff5f7819908be04282a093b8af"){
//                            $resign_url = "http://8.218.63.79:85/index/sign_resign_app";
//                            Logger::getInstance()->info("测试重签");
//                        }
//                        $rand = rand(0,10);
//                        if($rand<=4){
//                            $resign_url = "http://35.227.214.161/index/sign_resign_app";
//                        }else{
//                            $resign_url = "http://8.218.75.38:85/index/sign_resign_app";
//                        }
//                        /**使用谷歌重签**/
//                        $is_google_resign = DbManager::getInstance()->invoke(function ($client) {
//                            $data = SiteConfig::invoke($client)->where("name", "is_google_resign")
//                                ->get();
//                            if (!empty($data)) {
//                                return $data->toArray();
//                            } else {
//                                return null;
//                            }
//                        });
//                        if(!empty($is_google_resign) && $is_google_resign["value"]==1){
//                            $resign_url = "http://34.117.236.200/index/sign_resign_app";
//                        }else{
//                            $resign_url = "http://8.218.75.38:85/index/sign_resign_app";
//                        }
                    }
//                }
                $app = DbManager::getInstance()->invoke(function ($client) use ($data) {
                    $app = App::invoke($client)->where("id", $data["app_id"])
                        ->where("status", 1)
                        ->where("is_delete", 1)
                        ->get();
                    if (!empty($app)) {
                        return $app->toArray();
                    } else {
                        return null;
                    }
                });

                $account_id = $app["account_id"];
                $account = DbManager::getInstance()->invoke(function ($client)use($account_id) {
                    $data = Enterprise::invoke($client)->where('id', $account_id)->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
                if(empty($account)){
                    $account = DbManager::getInstance()->invoke(function ($client) {
                        $data = Enterprise::invoke($client)->where('status', 1)->get();
                        if (!empty($data)) {
                            return $data->toArray();
                        } else {
                            return null;
                        }
                    });
                }
                if (empty($app) || empty($account)) {
                    Logger::getInstance()->error("无APP数据或可用证书，分发结束");
                    return false;
                }
                /**是否传入公共库***/
                $is_public = DbManager::getInstance()->invoke(function ($client) {
                    $data = SiteConfig::invoke($client)->where("name", "is_g_ipa_public")
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
                /***记录重签唯一数**/
                $kevstoreidvf = $app["tag"].$this->getMicTime().rand(111,999);
                $resign_sign_data = [
                    "app_id" => $app["id"],
                    'udid' => $data["udid"],
                    "sign_str"=>$kevstoreidvf,
                    "create_time"=>date("Y-m-d H:i:s"),
                ];
                DbManager::getInstance()->invoke(function ($client)use($resign_sign_data){
                    AppResignInstallCallback::invoke($client)->where("app_id",$resign_sign_data["app_id"])
                        ->where("udid",$resign_sign_data["udid"])->destroy(null,true);
                });
                RedisPool::invoke(function (Redis $redis) use ($resign_sign_data) {
                    $redis->select(14);
                    $redis->del("app_resign_callback:" . $resign_sign_data["app_id"] . ":" . $resign_sign_data["udid"]."*");
                });

                DbManager::getInstance()->invoke(function ($client)use($resign_sign_data){
                    ResignSignStr::invoke($client)->data($resign_sign_data)->save();
                });
                $post_data = [
                    "app_id" => $app["id"],
                    'udid' => $data["udid"],
                    "append_data" => $data["append_data"],
                    "tag" => $app["tag"],
                    "is_mac" => $app["is_mac"],
                    "is_overseas" => $data["is_overseas"],
                    "app_path" => $app["oss_path"],
                    "package_name" => $app["package_name"],
                    "cert_path" => $account["oss_path"],
                    "oss_provisioning" => $account["oss_provisioning"],
                    "password" => $account["password"],
                    "kevstoreidvf" =>$kevstoreidvf,
                ];
                if (!empty($is_public) && $is_public["value"] == 1) {
                    $post_data["is_public"] = 1;
                } else {
                    $post_data["is_public"] = 0;
                }
                $result = $this->http_client($resign_url, $post_data);
                $status_code = $result->getStatusCode();
                if ($status_code == 200) {
                    RedisPool::invoke(function (Redis $redis) use ($data) {
                        $redis->select(9);
                        $redis->set($data["tag"] . ":" . $data["udid"], "重签任务已分发完成", 600);
                    });
                    $resign_success = true;
                } else {
                    co::sleep(3);
                    $result = $this->http_client($resign_url, $post_data);
                    $status_code = $result->getStatusCode();
                    if ($status_code == 200) {
                        RedisPool::invoke(function (Redis $redis) use ($data) {
                            $redis->select(9);
                            $redis->set($data["tag"] . ":" . $data["udid"], "重签任务已分发完成", 600);
                        });
                        $resign_success = true;
                    } else {
                        Logger::getInstance()->error("任务分发失败====$status_code==$resign_url===$val===APPid=" . $data["app_id"] . "==udid==" .$data["udid"] . "====" . $data['is_overseas']);
                        RedisPool::invoke(function (Redis $redis) use ($data) {
                            $redis->select(9);
                            $redis->set($data["tag"] . ":" . $data["udid"], "重签任务分发失败", 600);
                        });
                        $resign_success = false;
                    }
                }
                if($resign_success){
                    $resign_num = DbManager::getInstance()->invoke(function ($client)use($resign_sign_data){
                        $time = date("Y-m-d H:i:s",strtotime("-2 days"));
                      return  ResignSignStr::invoke($client)->where("app_id",$resign_sign_data["app_id"])
                            ->where("udid",$resign_sign_data["udid"])
                            ->where("is_used",1)
                            ->where("create_time",$time,">")
                            ->count();
                    });
                    if($resign_num>=10){
                        $url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
                        $post_data = [
                            "chat_id" => "-1001463689548",
                            "text" => "2.0 APP同一UDID重签超过10次,请注意查看; UDID: ".$data["udid"]." ;APP: ".$app["name"]."; APP_ID: ".$app["id"],
                        ];
                        $result = $this->http_client($url, $post_data);
                    }
                }
            } else {
                break;
            }
        }
        return true;
    }

    function http_client($url, $data = [], $header = [])
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

    function getMicTime(){
        $str = explode(" ",microtime());
        $time  = intval($str[1]).intval(floatval($str[0])*10000);
        return $time;
    }

}