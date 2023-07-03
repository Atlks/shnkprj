<?php


namespace App\Task;


use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class PushLoop implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {
        $error_num = 0;
        while (true){
            try {
                $data =   RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis){
                    $redis->select(2);
                    return $redis->lPop("push_list_task");
                });
                if(empty($data)){
                    break;
                }
                $data = json_decode($data,true);
                if ($data["topic"]=="com.apple.mgmt.External.179a2164-6c2e-4e9b-9b90-14c1ff97d269"){
                    $PEM = EASYSWOOLE_ROOT . "/other/push/m5/push.pem";
                }elseif ($data["topic"]=="com.apple.mgmt.External.3e2873ec-5182-4a6d-b842-ad4427424c37"){
                    $PEM = EASYSWOOLE_ROOT . "/other/push/m6/push.pem";
                }else{
                    continue;
                }
//                if($data["topic"]=="com.apple.mgmt.External.117afaf2-ca3d-44d6-b8ff-4c1c30d40458"){
//                    $PEM =ROOT_PATH . "/other/push/push.pem";
//                }elseif ($data["topic"]=="com.apple.mgmt.External.88a08591-083a-4c02-b3a2-78aef5b67371"){
//                    $PEM = ROOT_PATH . "/other/push/push_m3.pem";
//                }else{
//                    $PEM = ROOT_PATH . "/other/push/push_m2.pem";
//                }
                $package_name = $data["package_name"];
                foreach ($data["data"] as $v){
                    RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($v,$package_name) {
                        $redis->select(1);
                        $task_data = [
                            "udid" => $v["udid"],
                            "app_token" => $v["app_token"],
                            "app_id" => $v["app_id"]
                        ];
                        $redis->set("is_admin_push:" .  $v["udid"],$package_name,600);
                        $redis->set("task:" . $v["udid"],json_encode($task_data),600);
                    });
                }
                $push = new \App\Lib\Push($PEM);
                $push->push_list_message($data["data"]);
            }catch (\Throwable $throwable){
                var_dump($throwable->getMessage());
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
        var_dump($throwable->getMessage());
        // TODO: Implement onException() method.
    }

}