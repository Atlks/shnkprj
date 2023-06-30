<?php


namespace App\Task;


use App\Mode\App;
use App\Mode\BaleRate;
use App\Mode\User;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class CheckIsDownloadApp implements TaskInterface
{
    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    public function run(int $taskId, int $workerIndex)
    {
        $time = date("Y-m-d H:i:s",strtotime("-30 days"));
        $id = 0;
        $num = 0;
        while (true) {
            $num++;
            $list = null;
            $list = DbManager::getInstance()->invoke(function ($client) use ($id,$time) {
                $data = App::invoke($client)
                    ->where("is_download", 0)
                    ->where("is_delete", 1)
                    ->where("id", $id, '>')
                    ->where("create_time",$time,"<")
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
            /**忽略的用户**/
            $user_id_caches=[514,2930,1134,9804,9622,7347,3097,4323,604,769,5817,14182];
            foreach ($list as $k => $v) {
                $id = $v["id"];
//                /**删除或者暂停下载***/
//                if($v["is_delete"]==0||$v["is_download"]==1){
//                    DbManager::getInstance()->invoke(function ($client) use ($v) {
//                        App::invoke($client)->where('id', $v["id"])->update(["is_admin"=>0]);
//                    });
//                    continue;
//                }
                /**暂时忽略改用户**/
                if(in_array($v["user_id"],$user_id_caches)){
                    continue;
                }
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
                    DbManager::getInstance()->invoke(function ($client) use ($v) {
                        App::invoke($client)->where('id', $v["id"])->update(["is_auto_delete"=>1,"is_download"=>1]);
                    });
                    continue;
                }
                /***该用户跳过**/
                if(strrpos($user["username"], "faster") !== false){
                    continue;
                }
                /***分表**/
                $bale_rate_table = $this->getTable("proxy_bale_rate", $user["pid"]);
                $all_total = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table,$v,$time) {
                    return  BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id",$v["id"])
                        ->where("status",1)
                        ->where("create_time",$time,">")
                        ->count();
                });
                /**无下载**/
                if($all_total<=0){
                    DbManager::getInstance()->invoke(function ($client) use ($v) {
                        App::invoke($client)->where('id', $v["id"])->update(["is_auto_delete"=>1,"is_download"=>1]);
                    });
                }
            }
            if (empty($list) || count($list) < 300) {
                break;
            }
//            $last = end($list);
//            $id = $last["id"];
//            /***避免重复**/
//            if ($num > 20) {
//                break;
//            }
        }
        Logger::getInstance()->info("下载检测");
    }

    private function getTable($table = "", $user_id = "")
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), 10));
        return $table . "_" . $ext;
    }

}