<?php


namespace App\Task;


use App\Lib\RedisLib;
use App\Mode\UdidToken;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use Throwable;

class CheckUdidToken implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {
        $keys = RedisPool::invoke(function (Redis $redis) {
            $redis->select(1);
            return $redis->keys("start_udid:*");
        });
        foreach ($keys as $v) {
            $k_v = explode(":", $v);
            $val = RedisPool::invoke(function (Redis $redis) use ($v) {
                $redis->select(1);
                return $redis->get($v);
            });
            /**90S无响应**/
            if (!empty($val) && $val != 1 && ($val < (time() - 30))) {
                if (isset($k_v[1]) && !empty($k_v[1])) {
                    $udid = $k_v[1];
                    DbManager::getInstance()->invoke(function ($client) use ($udid) {
                        UdidToken::invoke($client)->where('udid', $udid)->update(['is_delete' => 0, "check_status" => 1, "delete_time" => date("Y-m-d H:i:s")]);
                    });
                    RedisLib::del("udidToken:" . $udid,2);
//                    $is_exit = RedisLib::hGetAll("udidToken:" . $udid, 2);
//                    if (!empty($is_exit)) {
//                        RedisLib::hUpdateVals("udidToken:" . $udid, ['is_delete' => 0, "check_status" => 1, "delete_time" => date("Y-m-d H:i:s")], 2);
//                    }
                    /**删除UDID初始记录时间**/
                    RedisPool::invoke(function (Redis $redis) use ($v) {
                        $redis->select(1);
                        $redis->del($v);
                    });
//                    Logger::getInstance()->error("udid 无响应==$udid==" . date("Y-m-d H:i:s", $val) . "==$val=");
                }
            }
        }
        return true;
    }

    public function onException(Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

}