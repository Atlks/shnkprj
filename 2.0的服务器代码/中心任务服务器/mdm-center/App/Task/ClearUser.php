<?php


namespace App\Task;


use App\Lib\Tool;
use App\Mode\BaleRate;
use App\Mode\User;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class ClearUser implements TaskInterface
{
    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
        var_dump($throwable->getMessage());
    }

    public function run(int $taskId, int $workerIndex)
    {
        $id = 0;
        $num = 0;
        $tool = new Tool();
        $time = date("2021-12-07");
        while (true){
            $list = DbManager::getInstance()->invoke(function ($client) use ($id) {
                $data = User::invoke($client)
                    ->where("id", $id, '>')
                    ->where("status","normal")
                    ->order("id", "ASC")
                    ->limit(0, 500)
                    ->field("id,sign_num,pid")
                    ->all();
                if ($data) {
                    return json_decode(json_encode($data), true);
                } else {
                    return [];
                }
            });
            foreach ($list as $v){
                $id = $v["id"];
                /***分表**/
                $bale_rate_table = $tool->getTable("proxy_bale_rate", $v["pid"]);
                $all_total = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table,$v,$time) {
                    return  BaleRate::invoke($client)->tableName($bale_rate_table)->where("user_id",$v["id"])
                        ->where("status",1)
//                        ->where("create_time",$time,">")
                        ->count();
                });
                if($all_total<50 && $v["sign_num"]<6){
                    $num++;
                    DbManager::getInstance()->invoke(function ($client) use ($v) {
                        User::invoke($client)->where('id', $v["id"])->update(["status"=>"hidden"]);
                    });
                    Logger::getInstance()->info(" 删除用户下载： $all_total ; $id");
                }
            }
            if (empty($list) || count($list) < 300) {
                break;
            }
        }
        Logger::getInstance()->info(" 删除用户筛选完成： $num");
        return true;
    }

}