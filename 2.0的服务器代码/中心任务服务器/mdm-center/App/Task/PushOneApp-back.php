<?php

namespace App\Task;

use App\Mode\BaleRate;
use App\Mode\UdidToken;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class PushOneApp  implements TaskInterface
{

    protected $taskData;

    public function __construct($data = [])
    {   
        $this->taskData = $data;
    }

    public function run(int $taskId, int $workerIndex)
    {
        // TODO: Implement run() method.
        $package_name = "com.HG9393.sports.ProdC1"; 
        $app_id = "124067";
        $max = 300000; //推送量
        $u_1id = 0;
        $total = 0;
        while (true) {
            $push_list = DbManager::getInstance()->invoke(function ($client) use ($u_1id) {
                $data = UdidToken::invoke($client)
                    ->where("is_delete", 1)
                    ->where("id", $u_1id, ">")
                    ->where("topic", "com.apple.mgmt.External.3e2873ec-5182-4a6d-b842-ad4427424c37")
                    ->order("id", "ASC")
                    ->field("id,udid,topic,udid_token,push_magic,app_token")
                    ->limit(0, 50)
                    ->all();
                if ($data) {
                    return json_decode(json_encode($data), true);
                } else {
                    return null;
                }
            });
            if (!empty($push_list)) {
                foreach ($push_list as $k => $v) {
                    $u_1id = $v["id"];
                    $is_cn = $this->is_exit_CN($v["udid"]);
                    if ($is_cn) {
                        $push_list[$k]["app_id"] = $app_id;
                        RedisPool::invoke(function (Redis $redis) use ($v) {
                            $redis->select(12);
                            $redis->rpush("push_list_udid" , $v["udid"]);
                        });
                    } else {
                        unset($push_list[$k]);
                    }
                }
                $push_data = [
                    "package_name" => $package_name,
                    "topic" => "com.apple.mgmt.External.3e2873ec-5182-4a6d-b842-ad4427424c37",
                    "data" => $push_list
                ];
                RedisPool::invoke(function (Redis $redis) use ($push_data) {
                    $redis->select(2);
                    $redis->rPush("push_list_task", json_encode($push_data));
                });
                $total =  RedisPool::invoke(function (Redis $redis) {
                    $redis->select(12);
                   return $redis->lLen("push_list_udid");
                });
                if($total>=$max){
                    break;
                }
            }else{
                break;
            }
            Logger::getInstance()->info("指定推送ID===1== $u_1id");
        }

        if($total<$max){
            $u_1id = 0;
            while (true) {
                $push_list = DbManager::getInstance()->invoke(function ($client) use ($u_1id) {
                    $data = UdidToken::invoke($client)
                        ->where("is_delete", 1)
                        ->where("id", $u_1id, ">")
                        ->where("topic", "com.apple.mgmt.External.179a2164-6c2e-4e9b-9b90-14c1ff97d269")
                        ->order("id", "ASC")
                        ->field("id,udid,topic,udid_token,push_magic,app_token")
                        ->limit(0, 50)
                        ->all();
                    if ($data) {
                        return json_decode(json_encode($data), true);
                    } else {
                        return null;
                    }
                });
                if (!empty($push_list)) {
                    foreach ($push_list as $k => $v) {
                        $u_1id = $v["id"];
                        $is_cn = $this->is_exit_CN($v["udid"]);
                        if ($is_cn) {
                            $push_list[$k]["app_id"] = $app_id;
                            RedisPool::invoke(function (Redis $redis) use ($v) {
                                $redis->select(12);
                                $redis->rpush("push_list_udid" , $v["udid"]);
                            });
                        } else {
                            unset($push_list[$k]);
                        }
                    }
                    $push_data = [
                        "package_name" => $package_name,
                        "topic" => "com.apple.mgmt.External.179a2164-6c2e-4e9b-9b90-14c1ff97d269",
                        "data" => $push_list
                    ];
                    RedisPool::invoke(function (Redis $redis) use ($push_data) {
                        $redis->select(2);
                        $redis->rPush("push_list_task", json_encode($push_data));
                    });
                    $total =  RedisPool::invoke(function (Redis $redis) {
                        $redis->select(12);
                        return $redis->lLen("push_list_udid");
                    });
                    if($total>=$max){
                        break;
                    }
                }else{
                    break;
                }
                Logger::getInstance()->info("指定推送ID===2== $u_1id");
            }
        }
        Logger::getInstance()->info("指定推送 $app_id  推送完成 总量 $total ");
        return true;
    }

    public function is_exit_CN($udid){
        $is_cn=false;
        for ($i=1;$i<=9;$i++){
            $table = "proxy_bale_rate_".$i;
            $is_exit = DbManager::getInstance()->invoke(function ($client) use ($udid,$table) {
                $data = BaleRate::invoke($client)->tableName($table)
                    ->where("udid", $udid)
                    ->where("status", 1)
                    ->where("is_auto", 0)
                    ->where("is_overseas", 10)
                    ->get();
                if ($data) {
                    return true;
                } else {
                    return false;
                }
            });
            if($is_exit){
                $is_cn=true;
                break;
            }
        }
        return $is_cn;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        var_dump($throwable->getMessage());
    }
}