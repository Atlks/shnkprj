<?php


namespace App\Task;


use App\Lib\RedisLib;
use App\Mode\App;
use App\Mode\AppInstallCallback;
use App\Mode\BaleRate;
use App\Mode\User;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class AppDownloadCheck implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {
//        $list = DbManager::getInstance()->invoke(function ($client) {
//            return  App::invoke($client)
//                ->where("status",1)
//                ->where("is_delete",1)
//                ->where("is_abnormal",0)
//                ->where("is_download",0)
//                ->where("is_resign",0)
//                ->where("pay_num",500,">=")
//                ->field("id,name,pay_num,user_id")
//                ->all();
//        });
        $list = DbManager::getInstance()->invoke(function ($client) {
            return  App::invoke($client)
                ->where("status",1)
                ->where("is_delete",1)
                ->where("is_abnormal",0)
                ->where("is_download",0)
                ->where("is_resign",1)
//                ->where("pay_num",500,">=")
                ->field("id,name,pay_num,user_id")
                ->all();
        });
        if(empty($list)){
            return true;
        }
        $list = json_decode(json_encode($list), true);
        $url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
        foreach ($list as $k=>$v){
            if($v["pay_num"]>20000){
                continue;
            }
            /***异常自动开启重签**/
            $post = [
                'app_id' => $v['id'],
                "key"=>md5($v['id']."iossign"),
                "is_resign"=>0
            ];
            $async = $this->http_client("http://8.218.63.79:85/index/alib_app", $post);
            $status_code = $async->getStatusCode();
            Logger::getInstance()->info("关闭重签注入===".$v['id']."===$status_code=");
//            $user = RedisLib::get("user_userId:".trim($v["user_id"]),4);
//            if(empty($user)) {
//                $user = DbManager::getInstance()->invoke(function ($client) use ($v) {
//                    $data = User::invoke($client)->where('id', $v["user_id"])
//                        ->where("status", "normal")
//                        ->get();
//                    if (!empty($data)) {
//                        return $data->toArray();
//                    } else {
//                        return 1;
//                    }
//                });
//                RedisLib::set("user_userId:".trim($v["user_id"]),$user,4,600);
//            }
//            if (empty($user)||!is_array($user)) {
//               continue;
//            }
//            $bale_rate_table = $this->getTable("proxy_bale_rate", $user["pid"]);
//            /**下载总数**/
//            $total = DbManager::getInstance()->invoke(function ($client) use ($v, $bale_rate_table) {
//                return BaleRate::invoke($client)->tableName($bale_rate_table)
//                    ->where('app_id', $v["id"])
//                    ->where("is_auto",0)
//                    ->count("id");
//            });
//            /**使用总数**/
//            $user_total = DbManager::getInstance()->invoke(function ($client)use($v){
//                return AppInstallCallback::invoke($client)->where("app_id",$v["id"])
//                    ->count("id");
//            });
//            /**使用比例超过80%**/
//            if(ceil(($user_total/$total)*100)>80&&ceil(($user_total/$total)*100)<90){
//                /***异常自动开启重签**/
//                $post = [
//                    'app_id' => $v['id'],
//                    "key"=>md5($v['id']."iossign"),
//                    "is_resign"=>1
//                ];
//                $async = $this->http_client("http://8.218.63.79:85/index/alib_app", $post);
//
//                $post_data = [
//                    "chat_id" => "-1001463689548",
//                    "text" => "2.0 APP下载异常提醒：APP< " . $v["name"] . " > APPID < " . $v["id"]. " > 使用率超过 95% ，已自动开启重签" ,
//                ];
//                $result = $this->http_client($url, $post_data);
//            }
        }
//        Logger::getInstance()->info("APP异常下载检测===");
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
        $client->setTimeout(-1);
        if (!empty($header)) {
            $client->setHeaders($header);
        }
        if (!empty($data)) {
//            $client->setContentTypeFormData();
            $result = $client->post($data);
        } else {
            $result = $client->get();
        }
        return $result;
    }


    protected function getTable($table, $user_id, $sn = 10)
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), $sn));
        return $table . "_" . $ext;
    }

}