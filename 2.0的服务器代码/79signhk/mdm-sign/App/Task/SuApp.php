<?php


namespace App\Task;


use App\Mode\App;
use App\Mode\BaleRate;
use App\Mode\UdidList;
use App\Mode\User;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class SuApp implements TaskInterface
{

    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;

    }

    public function run(int $taskId, int $workerIndex)
    {
        $user_id = $this->taskData["user_id"];
        $num = $this->taskData["num"];
        $start_num = 0;
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
        if ( empty($user)) {
            /***应用不存在**/
            return true;
        }
        $app_list = DbManager::getInstance()->invoke(function ($client)use($user_id){
              $data = App::invoke($client)->where("user_id", $user_id)
                  ->where('status', 1)
                  ->where('is_delete', 1)
                  ->where('is_download', 0)
                  ->field("id,name,user_id,lang")
                  ->all();
              return  json_decode(json_encode($data),true);
        });
        $bale_rate_table = $this->getTable("proxy_bale_rate", $user["pid"]);

        while (true){
            $udids =  DbManager::getInstance()->invoke(function ($client){
                $offset = rand(0,1113000-100);
                return UdidList::invoke($client)->limit($offset,100)->all();
            });
            foreach ($udids as $k=>$v){
                $app = $app_list[array_rand($app_list)];
                $ip = $this->getIp($app["lang"]);
                $create_time = date('Y-m-d H:i:s', time() - rand(600, 43200));
                $bale_rate=[
                    'app_id' => $app['id'],
                    'udid' => $v["udid"],
                    'resign_udid' => $v["udid"],
                    'user_id' => $user['id'],
                    'rate' => $user['rate'],
                    'pid' => $user['pid'],
                    'status' => 1,
                    'create_time' =>$create_time,
                    'update_time' => $create_time,
                    'account_id' => 0,
                    'ip' => empty($ip)?$v["ip"]:$ip,
                    'device' => $v["device"],
                    'sign_num' => 1,
                    'is_overseas' => 10,
                    'is_auto'=>1
                ];
                DbManager::getInstance()->invoke(function ($client)use($user_id,$bale_rate_table,$bale_rate,$app){
                    App::invoke($client)->where('id', $app["id"])->update(['pay_num' => QueryBuilder::inc(1),'download_num' => QueryBuilder::inc(1)]);
                    User::invoke($client)->where('id', $user_id)->update(['sign_num' => QueryBuilder::dec(1)]);
                    BaleRate::invoke($client)->tableName($bale_rate_table)->data($bale_rate)->save();
                });
                $start_num++;
                if($start_num>=$num){
                    Logger::getInstance()->info("SU:==$user_id==$start_num======$num====");
                    break;
                }
            }
            if($start_num>=$num){
                Logger::getInstance()->info("SU:==$user_id==$start_num======$num====");
                break;
            }
        }
        return true;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        Logger::getInstance()->error($throwable->getMessage());
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
        }else{
            return "";
        }
    }

}