<?php


namespace App\Task;


use App\Model\App;
use App\Model\AppInstallCallback;
use App\Model\BaleRate;
use App\Model\ProxyUser;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class CheckInstallApp implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {
        Logger::getInstance()->info("开始异常统计任务");
        $id = 0;
        $num = 0;
        $url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
        while (true) {
            $num++;
            $list = null;
            $list = DbManager::getInstance()->invoke(function ($client) use ($id) {
                $data = App::invoke($client)
                    ->where("status", 1)
                    ->where("is_delete", 1)
                    ->where("is_download", 0)
                    ->where("pay_num", 1, '>')
                    ->where("id", $id, '>')
                    ->order("id", "ASC")
                    ->limit(0, 500)
                    ->field("id,user_id,pay_num,name")
                    ->all();
                if ($data) {
                    return json_decode(json_encode($data), true);
                } else {
                    return null;
                }
            });
            if(empty($list)){
                break;
            }
            foreach ($list as $k => $v) {
                $user = DbManager::getInstance()->invoke(function ($client) use ($v) {
                    $data = ProxyUser::invoke($client)->where('id', $v["user_id"])
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
                /***分表**/
                $bale_rate_table = $this->getTable("proxy_bale_rate", $user["pid"]);
                $pay_all_num = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table, $v) {
                    return BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id", $v["id"])
                        ->where("user_id", $v["user_id"])
                        ->where("status", 1)
                        ->count();
                });
                $auto_num = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table, $v) {
                    return BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id", $v["id"])
                        ->where("user_id", $v["user_id"])
                        ->where("is_auto", 1)
                        ->where("status", 1)
                        ->count();
                });
                $install_num = DbManager::getInstance()->invoke(function ($client) use ($v) {
                    return AppInstallCallback::invoke($client)->where("app_id", $v["id"])
                        ->where("user_id", $v["user_id"])
                        ->count();
                });
//                $today_install_num =  DbManager::getInstance()->invoke(function ($client) use ($v) {
//                    return AppInstallCallback::invoke($client)->where("app_id", $v["id"])
//                        ->where("user_id", $v["user_id"])
//                        ->where("create_time", date("Y-m-d 00:00:00"),">")
//                        ->count();
//                });
//                $today_pay_num = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table, $v) {
//                    return BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id", $v["id"])
//                        ->where("user_id", $v["user_id"])
//                        ->where("status", 1)
//                        ->where("is_auto", 0)
//                        ->where("create_time", date("Y-m-d 00:00:00"),">")
//                        ->count();
//                });

                /**有刷***/
                if ($install_num > ($pay_all_num - $auto_num)) {
                    $update = [
                        "is_abnormal" => 1,
                        "abnormal_num" => $install_num - ($pay_all_num - $auto_num),
                        "install_num" => $install_num,
                        "pay_num"=>$pay_all_num
                    ];
                } else {
                    $update = [
                        "is_abnormal" => 0,
                        "abnormal_num" => 0,
                        "install_num" => $install_num,
                        "pay_num"=>$pay_all_num
                    ];
                }
                DbManager::getInstance()->invoke(function ($client) use ($v, $update) {
                    App::invoke($client)->where('id', $v["id"])->update($update);
                });
//                if(($today_install_num-$today_pay_num)>100){
//                    $post_data = [
//                        "chat_id" => "-1001463689548",
//                        "text" => "2.0 APP提醒：APP< " . $v["name"] . " > APPID < " . $v["id"]. " >当日使用量超过当日扣费次数 100次：" ,
//                    ];
//                    $result = $this->http_client($url, $post_data);
//                }
                Logger::getInstance()->info("异常统计任务====".$v["id"]);
            }
            if (empty($list) || count($list) < 300) {
                break;
            }
            $last = end($list);
            $id = $last["id"];
        }
        Logger::getInstance()->info("异常统计任务结束");
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
        Logger::getInstance()->error($throwable->getMessage());
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