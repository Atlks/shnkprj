<?php


namespace App\Task;


use App\Mode\ProxyUser;
use App\Mode\ProxyUserDomain;
use App\Mode\ProxyUserMoneyNotice;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class AutoNotice implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {
        $list = DbManager::getInstance()->invoke(function ($client) {
            return ProxyUserMoneyNotice::invoke($client)
                ->where('status', 1)
                ->field("id,user_id,sign_num,status,chat_id,times")
                ->all();
        });
        $list = json_decode(json_encode($list), true);
        $user_ids = array_column($list, "user_id");
        if (!empty($user_ids)) {
            $user_list = DbManager::getInstance()->invoke(function ($client) use ($user_ids) {
                return ProxyUser::invoke($client)->where('status', "normal")
                    ->field("id,sign_num,username,pid")
                    ->all();
            });
            $user_list = json_decode(json_encode($user_list), true);
            $cache = [];
            foreach ($user_list as $k => $v) {
                $cache[$v["id"]] = $v;
            }
            foreach ($list as $k => $v) {
                if (array_key_exists($v["user_id"], $cache) && ($v["sign_num"] >= $cache[$v["user_id"]]["sign_num"])) {
                    if ($v["times"] > 2) {
                        continue;
                    } else {
                        DbManager::getInstance()->invoke(function ($client) use ($v) {
                            ProxyUserMoneyNotice::invoke($client)->where('id', $v["id"])->update(["times" => ($v["times"] + 1)]);
                        });
                    }
                    $pid = $cache[$v["user_id"]]["pid"];
                    $domain = DbManager::getInstance()->invoke(function ($client) use ($pid) {
                        return ProxyUser::invoke($client)->where('id', $pid)->get();
                    });
                    $business = isset($domain["username"]) ? $domain["username"] : "";
                    $url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
                    $post_data = [
                        "chat_id" => empty($v["chat_id"]) ? "-333087236" : $v["chat_id"],
                        "text" => "2.0 次数不足提醒：代理< " . $business . " > 下级用户 < " . $cache[$v["user_id"]]["username"] . " >次数不足：" . $v["sign_num"],
                    ];
                    $result = $this->http_client($url, $post_data);
                    continue;
                } else {
                    if ($v["times"] > 1) {
                        DbManager::getInstance()->invoke(function ($client) use ($v) {
                            ProxyUserMoneyNotice::invoke($client)->where('id', $v["id"])->update(["times" => 0]);
                        });
                    }
                }
            }
        }
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