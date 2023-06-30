<?php


namespace App\Task;


use App\Lib\GoogleOss;
use App\Mode\App;
use App\Mode\BaleRate;
use App\Mode\Enterprise;
use App\Mode\OssConfig;
use App\Mode\ProxyAppDiffDownload;
use App\Mode\ProxyRechargeLog;
use App\Mode\ProxyUserDomain;
use App\Mode\User;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class AppAllSign implements TaskInterface
{

    protected $taskData;

    public function __construct($data = [])
    {
        $this->taskData = $data;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
        var_dump($throwable->getMessage());
    }

    public function run(int $taskId, int $workerIndex)
    {
        $id = $this->taskData["id"];
        $num = 0;
        $account = DbManager::getInstance()->invoke(function ($client) {
            $data = Enterprise::invoke($client)->where('id', 70)->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
        if(empty($account)){
            Logger::getInstance()->error("批量签名无证书");
            return true;
        }
        while (true) {
            $list = null;
            $list = DbManager::getInstance()->invoke(function ($client) use ($id) {
                $data = App::invoke($client)
                    ->where("is_download", 0)
//                    ->where("is_resign", 0)
                    ->where("status", 1)
                    ->where("is_delete", 1)
                    ->where("account_id", 70,"<>")
                    ->where("id", $id, '>')
//                    ->where("update_time","2022-01-01  01:00:00",">")
                    ->order("id", "ASC")
                    ->limit(0, 50)
                    ->field("id,user_id,tag,oss_path,is_resign,account_id,package_name,is_delete,is_download")
                    ->all();
                if ($data) {
                    return json_decode(json_encode($data), true);
                } else {
                    return null;
                }
            });
            foreach ($list as $k => $v) {
                $id = $v["id"];
                if($v["account_id"]==70){
                    continue;
                }
                if(in_array($id,[80503,97828,111231])){
                    Logger::getInstance()->info("初始签名跳过：".$v["id"]."====== ");
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
                    continue;
                }
//                /**总充值**/
//                $all_pay = DbManager::getInstance()->invoke(function ($client) use ($v) {
//                    return ProxyRechargeLog::invoke($client)->where('user_id', $v["user_id"])
//                        ->where("type", [1,5],"IN")
//                        ->sum("num");
//                });
//                if($all_pay<200){
//                    continue;
//                }

                /***分表**/
                $bale_rate_table = $this->getTable("proxy_bale_rate", $user["pid"]);
                $total = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table,$v) {
                    return  BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id",$v["id"])
                        ->where("status",1)
                        ->where("is_auto",0)
                        ->where("create_time","2022-10-01  00:00:00",">")
                        ->count();
                });
                /**有下载量**/
                if($total<1){
                    continue;
                }
                if($v["is_resign"]==1){
                    DbManager::getInstance()->invoke(function ($client) use ($v) {
                        App::invoke($client)->where("id", $v["id"])
                            ->update(["account_id"=>70]);
                    });
                    continue;
                }
                $oss_path = 'app/' . date('Ymd') . '/' . $v["tag"] . '.ipa';
                $num++;
                /**OSS分流***/
                $async_oss_config =  DbManager::getInstance()->invoke(function ($client)use ($user){
                    $data = ProxyUserDomain::invoke($client)->where('user_id', $user["pid"])->get();
                    if($data["oss_id"]){
                        $async_oss_config = OssConfig::invoke($client)->where("id",$data["oss_id"])->get();
                        if(!empty($async_oss_config)){
                            return  $async_oss_config->toArray();
                        }
                    }
                    return null;
                });
                if($async_oss_config){
                    $oss_id = $async_oss_config["id"];
                }else{
                    $oss_id =0;
                }

                $ansyc_data = [
                    'path' => $v['oss_path'],
                    'oss_path' => $oss_path,
                    'cert_path' => $account["oss_path"],
                    'provisioning_path' => $account["oss_provisioning"],
                    'password' => $account["password"],
                    'account_id' => $account["id"],
                    'app_id' => $v['id'],
                    'package_name' => $v['package_name'],
                    'is_resign'=>$v["is_resign"],
                    "tag"=>$v["tag"],
                    "oss_id"=>$oss_id,
                    "async_oss_config"=>$async_oss_config
                ];

                $resign_url = "http://119.23.245.177:85/index/alib_int_app";
                $result = $this->http_client($resign_url, $ansyc_data);
                $status_code = $result->getStatusCode();
                if ($status_code == 200) {
                    Logger::getInstance()->info("初始签名分发完成：".$v["id"]."====== ".$num);
                }else{
                    Logger::getInstance()->error("初始签名分发失败：".$v["id"]."====== ".$num);
                }
            }
//            \co::sleep(10);
            if (empty($list) || count($list) < 10) {
                break;
            }
        }
        Logger::getInstance()->info("初始签名全部完成");
        return true;
    }

    private function getTable($table = "", $user_id = "")
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), 10));
        return $table . "_" . $ext;
    }

    function http_client($url, $data = [], $header = [])
    {
        $client = new HttpClient();
        $client->setUrl($url);
        $client->setTimeout(30);
        if (!empty($header)) {
            $client->setHeaders($header);
        }
        if (!empty($data)) {
            $result = $client->post($data);
        } else {
            $result = $client->get();
        }
        return $result;
    }

}