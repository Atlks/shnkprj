<?php


namespace App\Task;


use App\Mode\ProxyUserMoneyNotice;
use App\Mode\User;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class AppUserNotice implements TaskInterface
{

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
       var_dump($throwable->getMessage());
    }

    public function run(int $taskId, int $workerIndex)
    {
        $list = DbManager::getInstance()->invoke(function ($client) {
            return ProxyUserMoneyNotice::invoke($client)
                ->where('status', 1)
                ->where('chat_id', "-603260329")
                ->field("id,user_id,sign_num,status,chat_id,times")
                ->all();
        });
        $list = json_decode(json_encode($list), true);
        $user_ids = array_column($list, "user_id");
        $url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
        if (!empty($user_ids)) {
            $user_list = DbManager::getInstance()->invoke(function ($client) use ($user_ids) {
                return User::invoke($client)->where('status', "normal")
                    ->where("id",$user_ids,"IN")
                    ->field("id,sign_num,username,pid")
                    ->all();
            });
            $user_list = json_decode(json_encode($user_list), true);
            foreach ($user_list as $k=>$v){
                $post_data = [
                    "chat_id" => "-603260329",
                    "text" => "次数提醒： 用户 < ".$v["username"]." > 剩余次数： ".$v["sign_num"],
                ];
                $result = $this->http_client($url, $post_data);
            }
        }
        return true;
    }


    public function http_client($url, $data = [], $header = [])
    {
        $client = new HttpClient();
        $client->setUrl($url);
        $client->setTimeout(60);
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

}