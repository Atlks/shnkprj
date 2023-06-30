<?php


namespace App\Task;


use App\Mode\App;
use App\Mode\BaleRate;
use App\Mode\ProxyAppDiffDownload;
use App\Mode\User;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class AppDiff implements TaskInterface
{
    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    public function run(int $taskId, int $workerIndex)
    {
        $id = 0;
        $num = 0;
        while (true) {
            $num++;
            $list = null;
            $list = DbManager::getInstance()->invoke(function ($client) use ($id) {
                $data = App::invoke($client)
                    ->where("is_download", 0)
                    ->where("is_delete", 1)
                    ->where("id", $id, '>')
                    ->where("pay_num",1000,">")
                    ->order("id", "ASC")
                    ->limit(0, 500)
                    ->field("id,user_id,pay_num,is_delete,is_download")
                    ->all();
                if ($data) {
                    return json_decode(json_encode($data), true);
                } else {
                    return null;
                }
            });
            foreach ($list as $k => $v) {
                $id = $v["id"];
                $user = DbManager::getInstance()->invoke(function ($client) use ($v) {
                    $data = User::invoke($client)->where('id', $v["user_id"])
                        ->where("status", "normal")
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
                if (empty($user)) {
                    continue;
                }
                /***分表**/
                $bale_rate_table = $this->getTable("proxy_bale_rate", $user["pid"]);
                $li = [
                    "app_id"=>$v["id"],
                    "one_num"=>0,
                    "two_num"=>0,
                    "three_num"=>0,
                    "four_num"=>0,
                    "five_num"=>0,
                    "diff_num"=>0,
                    "update_time"=>date("Y-m-d H:i:s"),
                ];
                for ($i=1;$i<=5;$i++){
                    $time = date("Y-m-d",strtotime("-$i days"));
                    $total = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table,$v,$time) {
                        return  BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id",$v["id"])
                            ->where("status",1)
                            ->where("create_time",$time."  00:00:00",">")
                            ->where("create_time",$time."  23:59:59","<=")
                            ->count();
                    });
//                    if($total<300){
//                        continue 2;
//                    }
                    if($i==1){
                        $li["one_num"] = $total;
                    }
                    if($i==2){
                        $li["two_num"] = $total;
                    }
                    if($i==3){
                        $li["three_num"] = $total;
                    }
                    if($i==4){
                        $li["four_num"] = $total;
                    }
                    if($i==5){
                        $li["five_num"] = $total;
                    }
                }
                $li["diff_num"] =$li["one_num"]-$li["two_num"];
                DbManager::getInstance()->invoke(function ($client)use($v){
                    ProxyAppDiffDownload::invoke($client)->where("app_id",$v["id"])->destroy(null,true);
                });
                DbManager::getInstance()->invoke(function ($client)use($li){
                    ProxyAppDiffDownload::invoke($client)->data($li)->save();
                });
            }
            if (empty($list) || count($list) < 300) {
                break;
            }
        }
        Logger::getInstance()->info("差值计算完成");
        return true;
    }

    private function getTable($table = "", $user_id = "")
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), 10));
        return $table . "_" . $ext;
    }

}