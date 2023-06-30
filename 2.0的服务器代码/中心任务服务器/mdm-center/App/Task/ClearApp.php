<?php


namespace App\Task;


use App\Lib\Tool;
use App\Mode\App;
use App\Mode\AppInstallCallback;
use App\Mode\AppInstallError;
use App\Mode\AppResignInstallCallback;
use App\Mode\AppWhitelist;
use App\Mode\AutoAppRefush;
use App\Mode\BaleRate;
use App\Mode\ProxyAppApkDownloadLog;
use App\Mode\ProxyAppUpdateLog;
use App\Mode\ProxyAppViews;
use App\Mode\ProxyDownloadCodeList;
use App\Mode\User;
use App\Mode\WxAppView;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class ClearApp implements TaskInterface
{
    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    public function run(int $taskId, int $workerIndex)
    {
        $time = date("Y-m-d H:i:s",strtotime("-2 months"));
        $id = 0;
        $num = 0;
        $tool = new Tool();
        while (true){
            $num++;
            $list = null;
            $list = DbManager::getInstance()->invoke(function ($client) use ($id,$time) {
                $data = App::invoke($client)
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
            foreach ($list as $k => $v) {
                $id = $v["id"];
                if(in_array($v['user_id'],[51,1960,9341,7347,1134,5817])){
                    continue;
                }
                $user = DbManager::getInstance()->invoke(function ($client) use ($v) {
                    $data = User::invoke($client)->where('id', $v["user_id"])
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
                $bale_rate_table = $tool->getTable("proxy_bale_rate", $user["pid"]);
                $view_table = $tool->getTable("proxy_app_views", $user["pid"], 100);
                $all_total = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table,$v,$time) {
                    return  BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id",$v["id"])
                        ->where("status",1)
                        ->where("create_time",$time,">")
                        ->count();
                });
                /**无下载**/
                if($all_total<=0){
                    DbManager::getInstance()->invoke(function ($client) use ($id,$bale_rate_table,$view_table) {
                        BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id",$id)->destroy(null,true);
                        ProxyAppViews::invoke($client)->tableName($view_table)->where("app_id",$id)->destroy(null,true);
                        AppInstallCallback::invoke($client)->where("app_id",$id)->destroy(null,true);
                        AppInstallError::invoke($client)->where("app_id",$id)->destroy(null,true);
                        AppWhitelist::invoke($client)->where("app_id",$id)->destroy(null,true);
                        AutoAppRefush::invoke($client)->where("app_id",$id)->destroy(null,true);
                        ProxyAppApkDownloadLog::invoke($client)->where("app_id",$id)->destroy(null,true);
                        ProxyDownloadCodeList::invoke($client)->where("app_id",$id)->destroy(null,true);
                        WxAppView::invoke($client)->where("app_id",$id)->destroy(null,true);
                        ProxyAppUpdateLog::invoke($client)->where("app_id",$id)->destroy(null,true);
                        App::invoke($client)->where('id', $id)->destroy(null,true);
                        AppResignInstallCallback::invoke($client)->where("app_id",$id)->destroy(null,true);
                    });
                    Logger::getInstance()->info("APP_id:".$id." ;bale: $bale_rate_table ; views: $view_table ; 即将删除所有数据");
//                    DbManager::getInstance()->invoke(function ($client) use ($v) {
//                        App::invoke($client)->where('id', $v["id"])->update(["is_auto_delete"=>1,"is_download"=>1]);
//                    });
                }
            }
            if (empty($list) || count($list) < 300) {
                break;
            }
        }
        Logger::getInstance()->info(" 删除过期数据完成");
        return true;
    }


    private function getTable($table = "", $user_id = "")
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), 10));
        return $table . "_" . $ext;
    }

    public function clear_cache(){

    }

}