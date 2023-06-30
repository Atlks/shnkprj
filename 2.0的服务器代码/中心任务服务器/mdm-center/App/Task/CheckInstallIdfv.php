<?php


namespace App\Task;


use App\Mode\App;
use App\Mode\AppInstallCallback;
use App\Mode\AppResignInstallCallback;
use App\Mode\BaleRate;
use App\Mode\User;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class CheckInstallIdfv implements TaskInterface
{
    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    public function run(int $taskId, int $workerIndex)
    {
        //TODO::临时关闭
        return true;
        $msg_url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
        $time = date("Y-m-d 00:00:00");
        $list = DbManager::getInstance()->invoke(function ($client)  use($time){
            $data = AppInstallCallback::invoke($client)
                ->where("create_time",$time,">")
                ->group("app_id")
                ->field("app_id,user_id,count(id) as num")
                ->all();
            if ($data) {
                return json_decode(json_encode($data), true);
            } else {
                return null;
            }
        });
        foreach ($list as $v){
            $user = DbManager::getInstance()->invoke(function ($client) use ($v) {
                $data = User::invoke($client)->where('id', $v["user_id"])
                    ->where("status", "normal")
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
            if (empty($user)) {
                continue;
            }
            $app = DbManager::getInstance()->invoke(function ($client) use ($v) {
                $app = App::invoke($client)->where("id", $v["app_id"])
                    ->where("status", 1)
                    ->where("is_delete", 1)
                    ->get();
                if (!empty($app)) {
                    return $app->toArray();
                } else {
                    return null;
                }
            });
            if(empty($app)){
                continue;
            }
            if($app["is_resign"]==1) {
                $resign_install_num = DbManager::getInstance()->invoke(function ($client) use ($v) {
                    return AppResignInstallCallback::invoke($client)->where("app_id", $v["app_id"])
                        ->where("user_id", $v["user_id"])
                        ->count();
                });
                $use_num = $resign_install_num;
            }else{
                $use_num = $v["num"];
            }
            /***分表**/
            $bale_rate_table = $this->getTable("proxy_bale_rate", $user["pid"]);
            $pay_all_num = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table, $v,$time) {
                return BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id", $v["app_id"])
                    ->where("user_id", $v["user_id"])
                    ->where("status", 1)
                    ->where("is_auto", 0)
                    ->where("create_time",$time,">")
                    ->count();
            });
            $all_num = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table, $v) {
                return BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id", $v["app_id"])
                    ->where("user_id", $v["user_id"])
                    ->where("status", 1)
                    ->count();
            });
//            $use_num = $v["num"]+$resign_install_num;
            $diff_num = $use_num-$pay_all_num;
            if($diff_num>=50&&$all_num<10000){
//                Logger::getInstance()->info("APP使用量超标====".$v["app_id"]."===".$v["num"]."===$pay_all_num==".($v["num"]-$pay_all_num)."===");
                $post_data = [
                    "chat_id" => "-1001463689548",
                    "text" => "2.0 APP当日使用量超过下载量：APPID < " . $v["app_id"]. " >超出次数：" .$diff_num,
                ];
                $result = $this->http_client($msg_url, $post_data);
                /***异常自动开启重签**/
//                $post = [
//                    'app_id' => $v['app_id'],
//                    "key"=>md5($v['app_id']."iossign"),
//                    "is_resign"=>1
//                ];
//                $async = $this->http_client("http://8.218.63.79:85/index/alib_app", $post);

            }

        }
        return  true;
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