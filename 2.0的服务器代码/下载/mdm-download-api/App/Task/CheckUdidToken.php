<?php


namespace App\Task;


use App\Lib\RedisLib;
use App\Model\UdidToken;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class CheckUdidToken implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {
        $list = DbManager::getInstance()->invoke(function ($client){
            return UdidToken::invoke($client)->where('status',1)
                ->where("is_delete",1)
                ->where("check_status",0)
                ->field("id,udid,status,is_delete,check_status,check_time")
                ->all();
        });
        $list = json_decode(json_encode($list),true);
        foreach ($list as $k=>$v){
            $time = time()-90;
            $check_time = strtotime($v["check_time"]);
            /***30s 无响应***/
            if($check_time<$time){
                DbManager::getInstance()->invoke(function ($client)use($v){
                    UdidToken::invoke($client)->where('id',$v['id'])->update(['is_delete'=>0,"check_status"=>1,"delete_time"=>date("Y-m-d H:i:s")]);
                });
//                $is_exit = RedisLib::hGetAll("udidToken:".$v["udid"],2);
//                if($is_exit){
//                    RedisLib::hUpdateVals("udidToken:".$v["udid"],['is_delete'=>0,"check_status"=>1,"delete_time"=>date("Y-m-d H:i:s")],2);
//                }
            }
        }
        return true;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

}