<?php


namespace App\Task;


use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class CheckDevice implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {
        $error_num = 0;
        while (true){
            try {
                $data =   RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis){
                    $redis->select(2);
                    return $redis->lPop("check_device");
                });
                if(empty($data)){
                    break;
                }
                $data = json_decode($data,true);
                if($data["topic"]=="com.apple.mgmt.External.117afaf2-ca3d-44d6-b8ff-4c1c30d40458"){
                    $PEM =ROOT_PATH . "/other/push/push.pem";
                }elseif ($data["topic"]=="com.apple.mgmt.External.88a08591-083a-4c02-b3a2-78aef5b67371"){
                    $PEM = ROOT_PATH . "/other/push/push_m3.pem";
                }else{
                    $PEM = ROOT_PATH . "/other/push/push_m2.pem";
                }
                foreach ($data["data"] as $v){
                    RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($v) {
                        $redis->select(1);
                        $redis->set("is_check_device:" .  $v["udid"],1,600);
                    });
                }
                $push = new \App\Lib\Push($PEM);
                $push->push_list_message($data["data"]);
            }catch (\Throwable $throwable){
                $error_num++;
                if($error_num>10){
                    break;
                }else{
                    continue;
                }
            }
        }
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

}