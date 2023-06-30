<?php


namespace App\Task;


use App\Mode\App;
use App\Mode\AppWhitelist;
use App\Mode\AutoAppRefush;
use App\Mode\BaleRate;
use App\Mode\ProxyDownloadCodeList;
use App\Mode\UdidList;
use App\Mode\User;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Redis\Redis;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class AutoRefush implements TaskInterface
{

    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;

    }

    public function run(int $taskId, int $workerIndex)
    {
        $app_id = $this->taskData["app_id"];
        $is_auto = DbManager::getInstance()->invoke(function ($client)use($app_id){
              $data = AutoAppRefush::invoke($client)->where("app_id", $app_id)
                  ->where('status', 1)
                  ->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
        $app = DbManager::getInstance()->invoke(function ($client) use ($app_id) {
            $data = App::invoke($client)->where('id', $app_id)
                ->where("is_delete", 1)
                ->where("status", 1)
                ->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
        $user = DbManager::getInstance()->invoke(function ($client) use ($app) {
            $data = User::invoke($client)->where('id', $app["user_id"])
                ->where("status", "normal")
                ->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
        if (empty($app) || empty($user)) {
            /***应用不存在**/
            return true;
        }
        /***用户白名单**/
        if($user["is_white"]==1){
            return true;
        }
        if (empty($is_auto) || $is_auto['scale'] == 0) {
            return true;
        }
        if($user["pid"]==2352){
            $is_auto['scale']=10;
        }

        /**
         * 下载限制
         */
        $is_code = DbManager::getInstance()->invoke(function ($client)use($app_id){
            $data = ProxyDownloadCodeList::invoke($client)->where("app_id",$app_id)
                ->where('status',1)
                ->get();
            if (!empty($data)) {
                return true;
            } else {
                return false;
            }
        });
        if($is_code){
            return true;
        }
        $is_white =  DbManager::getInstance()->invoke(function ($client)use($app_id){
            $data = AppWhitelist::invoke($client)->where("app_id",$app_id)
                ->where('status',1)
                ->get();
            if (!empty($data)) {
                return true;
            } else {
                return false;
            }
        });
        if($is_white){
            return true;
        }
//        if(strtotime(date("Y-m-d 00:10:00"))<=time()){
//            /**已过凌晨***/
//            $time = date("Y-m-d 00:00:00");
//        }else{
//            $time = date("Y-m-d H:i:s",(time()-600));
//        }
        $time = date("Y-m-d H:i:s",(time()-(12*60*60)));
        /***分表**/
        $bale_rate_table = $this->getTable("proxy_bale_rate", $user["pid"]);
        $today_total = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table,$app_id,$time) {
            return  BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id",$app_id)
                ->where("status",1)
                ->where("create_time",$time,">")
                ->count();
        });
        $auto_total = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table,$app_id,$time) {
            return  BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id",$app_id)
                ->where("is_auto",1)
                ->where("status",1)
                ->where("create_time",$time,">")
                ->count();
        });
        /**5次以下不刷**/
        if($today_total<5) return true;
        $rand = floor(($auto_total/$today_total)*100);
        if ($is_auto['scale'] == 100 || $rand <= $is_auto['scale']) {
            if ($user['sign_num'] < 2) {
                return true;
            }
            $udids = RedisPool::invoke(function (Redis $redis) {
                $redis->select(10);
                return $redis->sRandMember("udid_list",100);
             });
            if(empty($udids)){
                $udids =  DbManager::getInstance()->invoke(function ($client){
                    $offset = rand(0,1113000-1000);
                    return UdidList::invoke($client)
                        ->where("id", $offset, ">")
                        ->where("id", ($offset+1000), "<=")
                        ->all();
                });
            }
            $info = null;
            foreach ($udids as $k=>$v){
                if(is_array($v)){
                    $cache_v = $v;
                }else{
                    $cache_v = json_decode($v,true);
                }
                $udid = $cache_v["udid"];
                $is_exit = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table,$app_id,$udid,$app) {
                    return  BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id",$app_id)
                        ->where("resign_udid",$udid)
                        ->get();
                });
                if ($is_exit) {
                    continue;
                } else {
                    $info = $cache_v;
                    break;
                }
            }
            if(empty($info)){
                return  true;
            }
            $ip = $this->getIp($app["lang"]);
            $create_time = date('Y-m-d H:i:s', time() - rand(100, 600));
            $update_time = date('Y-m-d H:i:s', time() + rand(100, 600));
            $bale_rate=[
                'app_id' => $app['id'],
                'udid' => $udid,
                'resign_udid' => $udid,
                'user_id' => $user['id'],
                'rate' => $user['rate'],
                'pid' => $user['pid'],
                'status' => 1,
                'create_time' =>$create_time,
                'update_time' => $update_time,
                'account_id' => 0,
                'ip' => empty($ip)?$info["ip"]:$ip,
                'device' => $info["device"],
                'sign_num' => 1,
                'is_overseas' => 10,
                'is_auto'=>1
            ];
            $user_id = $app["user_id"];
            $user = DbManager::getInstance()->invoke(function ($client) use ($user_id) {
                $data = User::invoke($client)->where('id', $user_id)
                    ->where("status", "normal")
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
            if ($user['sign_num'] < 2) {
                return true;
            }
            DbManager::getInstance()->invoke(function ($client)use($user_id,$bale_rate_table,$bale_rate,$app_id){
                App::invoke($client)->where('id', $app_id)->update(['pay_num' => QueryBuilder::inc(1),'download_num' => QueryBuilder::inc(1)]);
                User::invoke($client)->where('id', $user_id)->update(['sign_num' => QueryBuilder::dec(1)]);
                BaleRate::invoke($client)->tableName($bale_rate_table)->data($bale_rate)->save();
            });
        }
        return true;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    private function getTable($table = "", $user_id = "")
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), 10));
        return $table . "_" . $ext;
    }

    /***
     * 获取各国IP
     * @param string $lang
     * @return string
     */
    protected function getIp($lang=""){
        /***越南IP**/
        if($lang=="vi"){
            $ip_list=[113,171,14];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="113"){
                return '113.'.rand(160,191).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="171"){
                return '171.'.rand(224,255).".".rand(0,255).".".rand(0,255);
            }else{
                return '14.'.rand(160,191).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="id"){
            /***印度尼西亚**/
            $ip_list=[39,36,120];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="36"){
                return '36.'.rand(64,95).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="120"){
                return '120.'.rand(160,191).".".rand(0,255).".".rand(0,255);
            }else{
                return '39.'.rand(192,255).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="th"){
            /***泰语**/
            $ip_list=[171,58,118];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="171"){
                return '171.'.rand(96,103).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="58"){
                return '58.'.rand(8,11).".".rand(0,255).".".rand(0,255);
            }else{
                return '39.'.rand(172,175).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="ko"){
            /***韩语**/
            $ip_list=[211,14,121];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="211"){
                return $ip_one.'.'.rand(168,255).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="14"){
                return $ip_one.'.'.rand(32,95).".".rand(0,255).".".rand(0,255);
            }else{
                return $ip_one.'.'.rand(128,191).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="ja"){
            /***日本**/
            $ip_list=[125,126,133];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="125"){
                return $ip_one.".255.".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="126"){
                return $ip_one.'.'.rand(0,255).".".rand(0,255).".".rand(0,255);
            }else{
                return $ip_one.'.'.rand(0,255).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="hi"){
            /***印度**/
            $ip_list=[117,106,122];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="117"){
                return $ip_one.".".rand(192,255).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="106"){
                return $ip_one.'.'.rand(192,233).".".rand(0,255).".".rand(0,255);
            }else{
                return $ip_one.'.'.rand(106,187).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="zh"){
            /***印度**/
            $ip_list=[14,27,36,42,43,45,49,58,59,60,101,103,110,111,113,114,115,116,117,118,119,120,121,123,124,139,144,140,150,153,157,160,163,167,171,175,180,182,183,185,202,203,222];
            $ip_one = $ip_list[array_rand($ip_list)];
            return $ip_one.'.'.rand(0,255).".".rand(0,255).".".rand(0,255);
        }else{
            $lang = array_rand(["vi"=>1,"id"=>2,"th"=>3,"ko"=>4,"ja"=>6,"hi"=>5]);
            return $this->getIp($lang);
        }
    }

}