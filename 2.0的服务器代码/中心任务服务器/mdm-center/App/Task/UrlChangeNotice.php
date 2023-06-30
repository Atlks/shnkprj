<?php


namespace App\Task;


use App\Mode\App;
use App\Mode\ProxyUserMoneyNotice;
use App\Mode\User;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class UrlChangeNotice implements TaskInterface
{

    protected $taskData;

    public function __construct($data = [])
    {
        $this->taskData = $data;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        Logger::getInstance()->error("域名提醒错误 ：".$throwable->getMessage());
    }

    public function run(int $taskId, int $workerIndex)
    {
        $pid = $this->taskData["pid"];
        $wx_url = $this->taskData["wx_url"];
        $download_url = $this->taskData["download_url"];

        $url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
        $client = new HttpClient();
        $client->setUrl($url);
        $client->setTimeout(60);

         $user_list = DbManager::getInstance()->invoke(function ($client) use ($pid) {
             return User::invoke($client)->where('status', "normal")
                 ->where("pid",$pid)
                 ->where("is_change_url_notice",1)
                 ->column("id");
         });
        if(empty($user_list)){
            return true;
        }
        $send_list = DbManager::getInstance()->invoke(function ($client)use ($user_list) {
            return ProxyUserMoneyNotice::invoke($client)
                ->where('user_id', $user_list,"IN")
                ->field("id,user_id,sign_num,status,chat_id")
                ->all();
        });
        $list = json_decode(json_encode($send_list), true);
        foreach ($list as $v){
            if(empty($v["chat_id"])){
                continue;
            }
            $app_list = DbManager::getInstance()->invoke(function ($client)use($v){
                $list = App::invoke($client)
                    ->where("is_download", 0)
                    ->where("is_delete", 1)
                    ->where("status", 1)
                    ->where("user_id", $v["user_id"])
                    ->field("id,name,short_url,remark")
                    ->all();
                if ($list) {
                    return json_decode(json_encode($list), true);
                } else {
                    return null;
                }
            });
            if(empty($app_list)){
                continue;
            }
            foreach ($app_list as $val){
                $result = null;
                if(!empty($wx_url)){
                    $post_data = [
                        "chat_id" =>$v["chat_id"],
                        "text" => "APP: ".$val["name"]."  , 微信防封域名已更新,最新地址： https://$wx_url/".$val["short_url"] .'  ;备注： '.$val["remark"],
                        ];
                    $result  = $client->post($post_data);
                }
                if(!empty($download_url)){
                    $post_data = [
                        "chat_id" =>$v["chat_id"],
                        "text" => "APP: ".$val["name"]."  , 下载域名已更新,最新地址： https://$download_url/".$val["short_url"] .'  ;备注： '.$val["remark"],
                        ];
                    $result  = $client->post($post_data);
                }
            }
        }
        return true;
    }
}