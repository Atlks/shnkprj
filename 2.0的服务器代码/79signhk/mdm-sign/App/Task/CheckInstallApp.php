<?php


namespace App\Task;


use App\Mode\App;
use App\Mode\AppInstallCallback;
use App\Mode\BaleRate;
use App\Mode\User;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class CheckInstallApp implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {
        Logger::getInstance()->info("开始异常统计任务");
        $id = 0;
        $num = 0;
        while (true) {
            $num++;
            $list = null;
            $list = DbManager::getInstance()->invoke(function ($client) use ($id) {
                $data = App::invoke($client)
                    ->where("status", 1)
                    ->where("is_delete", 1)
                    ->where("pay_num", 1, '>')
                    ->where("id", $id, '>')
                    ->order("id", "ASC")
                    ->limit(0, 500)
                    ->field("id,user_id,pay_num")
                    ->all();
                if ($data) {
                    return json_decode(json_encode($data), true);
                } else {
                    return null;
                }
            });
            foreach ($list as $k => $v) {
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
                $pay_all_num = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table, $v) {
                    return BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id", $v["id"])
                        ->where("user_id", $v["user_id"])
                        ->where("status", 1)
                        ->count();
                });
                $auto_num = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table, $v) {
                    return BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id", $v["id"])
                        ->where("user_id", $v["user_id"])
                        ->where("is_auto", 1)
                        ->where("status", 1)
                        ->count();
                });
                $install_num = DbManager::getInstance()->invoke(function ($client) use ($v) {
                    return AppInstallCallback::invoke($client)->where("app_id", $v["id"])
                        ->where("user_id", $v["user_id"])
                        ->count();
                });
                /**有刷***/
                if ($install_num > ($pay_all_num - $auto_num)) {
                    $update = [
                        "is_abnormal" => 1,
                        "abnormal_num" => $install_num - ($pay_all_num - $auto_num),
                        "install_num" => $install_num
                    ];
                } else {
                    $update = [
                        "is_abnormal" => 0,
                        "abnormal_num" => 0,
                        "install_num" => $install_num
                    ];
                }
                DbManager::getInstance()->invoke(function ($client) use ($v, $update) {
                    App::invoke($client)->where('id', $v["id"])->update($update);
                });
            }
            if (empty($list) || count($list) < 300) {
                break;
            }
            $last = end($list);
            $id = $last["id"];
            /***避免重复**/
            if ($num > 20) {
                break;
            }
        }
        Logger::getInstance()->info("异常统计任务结束");
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
        Logger::getInstance()->error($throwable->getMessage());
    }

    private function getTable($table = "", $user_id = "")
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), 10));
        return $table . "_" . $ext;
    }

}