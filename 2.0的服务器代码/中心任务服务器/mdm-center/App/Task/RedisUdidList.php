<?php


namespace App\Task;


use App\Mode\UdidList;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class RedisUdidList implements TaskInterface
{
    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    public function run(int $taskId, int $workerIndex)
    {
        $all_num = 1113543;
        $id = 0;
        $num = 0;
        while (true) {
            $num++;
            $offset = $id + 1000;
            $udids = DbManager::getInstance()->invoke(function ($client) use ($id, $offset) {
                return UdidList::invoke($client)
                    ->where("id", $id, ">")
                    ->where("id", $offset, "<=")
                    ->all();
            });
            $redis = RedisPool::defer();
            $redis->select(10);
            foreach ($udids as $k => $v) {
                if (!empty($v)) {
                    $redis->sAdd("udid_list", json_encode($v));
                }
            }
            $id+=1000;
            if(count($udids)<100){
                break;
            }
        }
        return true;
    }

}