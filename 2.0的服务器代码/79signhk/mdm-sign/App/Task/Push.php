<?php


namespace App\Task;


use App\Lib\Redis;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class Push implements TaskInterface
{

    //PUSH地址
    private $push_url = 'ssl://gateway.push.apple.com:2195';

    private $push_ssl = null;

    public function run(int $taskId, int $workerIndex)
    {
        $error_num = 0;
        while (true){
            try {
                $data =   RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis){
                    $redis->select(2);
                    return $redis->lPop("push_task");
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
                RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($data) {
                    $redis->select(1);
                    $task_data = [
                        "udid" => $data["udid"],
                        "app_token" => $data["app_token"],
                        "app_id" => $data["app_id"]
                    ];
//                    $redis->set("is_task:" . $data["udid"],1,600);
                    $redis->set("is_admin_push:" .  $data["udid"],$data["package_name"],600);
                    $redis->set("task:" . $data["udid"],json_encode($task_data),600);
                });
                $push = new \App\Lib\Push($PEM);
                $push->startMdm($data["udid_token"], $data["push_magic"]);
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
        var_dump($throwable->getMessage());
    }


}