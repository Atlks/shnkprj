<?php


namespace App\Task;


use App\Lib\GoogleOss;
use App\Mode\App;
use App\Mode\BaleRate;
use App\Mode\Enterprise;
use App\Mode\OssConfig;
use App\Mode\ProxyAppDiffDownload;
use App\Mode\ProxyUserDomain;
use App\Mode\User;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class AppPush implements TaskInterface
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
        $id = $this->taskData["id"];
        Logger::getInstance()->info("推送任务开始：".$id);
        $app = DbManager::getInstance()->invoke(function ($client) use ($id) {
            $data = App::invoke($client)
                ->where("is_resign", 0)
                ->where("is_download", 0)
                ->where("is_delete", 1)
                ->where("id", $id)
                ->field("id,user_id,tag,oss_path,is_resign,package_name,is_delete,is_download")
                ->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
        if(empty($app)){
            return  true;
        }
        $user = DbManager::getInstance()->invoke(function ($client) use ($app) {
            $data = User::invoke($client)->where('id', $app["user_id"])
                ->where("status", "normal")
                ->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
        if (empty($user)) {
            return true;
        }
        /***分表**/
        $bale_rate_table = $this->getTable("proxy_bale_rate", $user["pid"]);
        $u_1id = 0;
        while (true){
            $push_list = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table,$app,$u_1id) {
                return  BaleRate::invoke($client)->tableName($bale_rate_table)
                    ->alias("b")
                    ->join("udid_token u","b.udid=u.udid","LEFT")
                    ->where("b.app_id",$app["id"])
                    ->where("b.id",$u_1id,">")
                    ->where("b.status",1)
                    ->where("b.is_auto",0)
                    ->where("u.is_delete",1)
                    ->where("u.topic","com.apple.mgmt.External.3e2873ec-5182-4a6d-b842-ad4427424c37")
                    ->where("b.update_time",date("Y-m-d 00:00:00",strtotime("-3 days")),">")
//                    ->where("b.update_time","2021-10-13 08:55:00","<")
                    ->order("b.id", "ASC")
                    ->limit(0, 50)
                    ->field("b.id,u.udid,u.topic,u.udid_token,u.push_magic,u.app_token,b.app_id")
                    ->all();
            });
            $push_list = json_decode(json_encode($push_list));
            if(!empty($push_list)){
                Logger::getInstance()->info(json_encode($push_list));
                $push_data = [
                    "package_name"=>$app["package_name"],
                    "topic"=>"com.apple.mgmt.External.3e2873ec-5182-4a6d-b842-ad4427424c37",
                    "data"=>$push_list
                ];
                RedisPool::invoke(function (Redis $redis) use ($push_data) {
                    $redis->select(2);
                    $redis->rPush("push_list_task", json_encode($push_data));
                });
                $last = end($push_list);
                $u_1id = $last->id;
            }
            Logger::getInstance()->info("推送任务：".$app["id"]."===u1id==== ".$u_1id);
            if(empty($push_list)||count($push_list)<30){
                break;
            }
        }
        $result = $this->http_client("http://34.150.103.114/index/push_list_app");
        $status_code = $result->getStatusCode();
//        \co::sleep(5);
//        $u_2id =0;
//        while (true){
//            $push_list = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table,$app,$u_2id) {
//                return  BaleRate::invoke($client)->tableName($bale_rate_table)
//                    ->alias("b")
//                    ->join("udid_token u","b.udid=u.udid","LEFT")
//                    ->where("b.app_id",$app["id"])
//                    ->where("b.id",$u_2id,">")
//                    ->where("b.status",1)
//                    ->where("b.is_auto",0)
//                    ->where("u.is_delete",1)
//                    ->where("u.topic","com.apple.mgmt.External.f8cee548-098d-40b7-8514-05fe016c34aa")
//                    ->where("b.update_time",date("Y-m-d 00:00:00",strtotime("-3 days")),">")
////                    ->where("b.update_time","2021-10-13 08:55:00","<")
//                    ->order("b.id", "ASC")
//                    ->limit(0, 50)
//                    ->field("b.id,u.udid,u.topic,u.udid_token,u.push_magic,u.app_token,b.app_id")
//                    ->all();
//            });
//            $push_list = json_decode(json_encode($push_list));
//            if(!empty($push_list)){
//                $push_data = [
//                    "package_name"=>$app["package_name"],
//                    "topic"=>"com.apple.mgmt.External.f8cee548-098d-40b7-8514-05fe016c34aa",
//                    "data"=>$push_list
//                ];
//                RedisPool::invoke(function (Redis $redis) use ($push_data) {
//                    $redis->select(2);
//                    $redis->rPush("push_list_task", json_encode($push_data));
//                });
//                $last = end($push_list);
//                $u_2id = $last->id;
//            }
//            Logger::getInstance()->info("推送任务：".$app["id"]."===u2id==== ".$u_2id);
//            if(empty($push_list)||count($push_list)<30){
//                break;
//            }
//        }
//        $result = $this->http_client("http://8.218.63.79:85/index/push_list_app");
//        $status_code = $result->getStatusCode();
//        \co::sleep(5);
//        $u_3id=0;
//        while (true){
//            $push_list = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table,$app,$u_3id) {
//                return  BaleRate::invoke($client)->tableName($bale_rate_table)
//                    ->alias("b")
//                    ->join("udid_token u","b.udid=u.udid","LEFT")
//                    ->where("b.app_id",$app["id"])
//                    ->where("b.id",$u_3id,">")
//                    ->where("b.status",1)
//                    ->where("b.is_auto",0)
//                    ->where("u.is_delete",1)
//                    ->where("u.topic","com.apple.mgmt.External.88a08591-083a-4c02-b3a2-78aef5b67371")
//                    ->where("b.update_time",date("Y-m-d 00:00:00",strtotime("-3 days")),">")
////                    ->where("b.update_time","2021-10-13 08:55:00","<")
//                    ->order("b.id", "ASC")
//                    ->limit(0, 50)
//                    ->field("b.id,u.udid,u.topic,u.udid_token,u.push_magic,u.app_token,b.app_id")
//                    ->all();
//            });
//            $push_list = json_decode(json_encode($push_list));
//            if(!empty($push_list)){
//                $push_data = [
//                    "package_name"=>$app["package_name"],
//                    "topic"=>"com.apple.mgmt.External.88a08591-083a-4c02-b3a2-78aef5b67371",
//                    "data"=>$push_list
//                ];
//                RedisPool::invoke(function (Redis $redis) use ($push_data) {
//                    $redis->select(2);
//                    $redis->rPush("push_list_task", json_encode($push_data));
//                });
//                $last = end($push_list);
//                $u_3id = $last->id;
//            }
//            Logger::getInstance()->info("推送任务：".$app["id"]."===u3id==== ".$u_3id);
//            if(empty($push_list)||count($push_list)<30){
//                break;
//            }
//        }

//        $result = $this->http_client("http://8.218.63.79:85/index/push_list_app");
//        $status_code = $result->getStatusCode();
        if ($status_code == 200) {
            Logger::getInstance()->info("推送任务提交：".$app["id"]."==== ");
        }else{
            Logger::getInstance()->error("推送任务提交：".$app["id"]."==== ");
        }
        return true;
    }

    private function getTable($table = "", $user_id = "")
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), 10));
        return $table . "_" . $ext;
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

}