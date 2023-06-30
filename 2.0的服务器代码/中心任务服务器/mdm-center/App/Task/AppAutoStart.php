<?php

namespace App\Task;

use App\Mode\App;
use App\Mode\ProxyAppEarlyWarning;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class AppAutoStart implements TaskInterface
{
    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        var_dump($throwable->getMessage());
        // TODO: Implement onException() method.
    }

    public function run(int $taskId, int $workerIndex)
    {
        $list = DbManager::getInstance()->invoke(function ($client){
            $data = ProxyAppEarlyWarning::invoke($client)->where("is_auto_start",1)
                    ->all();
            if ($data) {
                return json_decode(json_encode($data), true);
            } else {
                return null;
            }
        });
        if (empty($list)) {
           return true;
        }
        $time = date("H");
        foreach ($list as $v){
            $is_stop =3;
            if(intval($v["stop_time"])==intval($time)){
                /**下架**/
                $is_stop =1;
            }
            if(intval($v["start_time"])==intval($time)){
                /**上架**/
                $is_stop =0;
            }
            /**不做任何处理***/
            if($is_stop==3){
                continue;
            }
            $app_id = $v["app_id"];
            if(empty($app_id)){
                continue;
            }
            $app = DbManager::getInstance()->invoke(function ($client) use ($app_id) {
                $app = App::invoke($client)->where("id", $app_id)
                    ->where("status", 1)
                    ->where("is_delete", 1)
                    ->field("id,name,tag,short_url,user_id,is_stop")
                    ->get();
                if (!empty($app)) {
                    return $app->toArray();
                } else {
                    return null;
                }
            });
            if(empty($app)||$app["is_stop"]==$is_stop){
                continue;
            }
            DbManager::getInstance()->invoke(function ($client)use ($app_id,$is_stop){
                App::invoke($client)->where('id', $app_id)->update(["is_stop"=>$is_stop]);
            });
            RedisPool::invoke(function (Redis $redis) use ($app) {
                $redis->select(4);
                $redis->del("app_tag:".$app["tag"]);
                $redis->del("app_short_url:".$app["short_url"]);
            });
        }
        return  true;
    }


}