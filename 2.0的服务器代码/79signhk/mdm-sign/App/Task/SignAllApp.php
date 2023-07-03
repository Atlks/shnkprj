<?php


namespace App\Task;


use App\Lib\Oss;
use App\Lib\Tool;
use App\Mode\App;
use App\Mode\Enterprise;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class SignAllApp implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {
        Logger::getInstance()->info("异步任务==".$taskId."===签名开始=====".date("Y-m-d H:i:s")."===");
        $sign = "/opt/zsign/zsign";
        $oss = new Oss();
        $tool = new Tool();
        $num=0;
        $account = DbManager::getInstance()->invoke(function ($client) {
            $data =  Enterprise::invoke($client)->where('status', 1)
                ->field("id,oss_path,password,oss_provisioning")
                ->get();
            if(empty($data)){
                return null;
            }else{
                return $data->toArray();
            }
        });
        if(empty($account)){
            return false;
        }
        $password = $account["password"];
        while (true){
            $data =   RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis){
                $redis->select(4);
                return $redis->rPop("sign_all_app");
            });
            if(empty($data)){
                break;
            }
            $data = json_decode($data,true);
            $path = ROOT_PATH."/cache/sign_all_app/". $data["tag"] . '/';
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
            $save_path = "app/".date("Ymd")."/".$data["tag"].".ipa";
            try {
                if(!$oss->ossDownload($data["oss_path"],$path."cache.ipa")){
                    Logger::getInstance()->info("ipa下载失败");
                    $tool->clearFile($path);
                   continue;
                }
                if(!$oss->ossDownload($account["oss_path"],$path."ios.p12")){
                    Logger::getInstance()->error("预签证书下载失败");
                    $tool->clearFile($path);
                    continue;
                }
                if(!$oss->ossDownload($account["oss_provisioning"],$path."ios.mobileprovision")){
                    Logger::getInstance()->error("预签描述文件下载失败");
                    $tool->clearFile($path);
                    continue;
                }
                if(empty($password)){
                    $shell =  "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p \"\" -o sign.ipa -z 1 cache.ipa";
                }else{
                    $shell = "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p $password -o sign.ipa -z 1 cache.ipa";
                }
                exec($shell,$log,$status);
                if(is_file($path."sign.ipa")) {
                    if ($oss->ossUpload($path . "sign.ipa", $save_path)) {

                        DbManager::getInstance()->invoke(function ($client) use ($data,$account,$save_path) {
                            App::invoke($client)->where("id", $data["id"])
                                ->update([
                                    "account_id" => $account["id"],
                                    "oss_path" => $save_path,
                                    "update_time" => date("Y-m-d H:i:s")
                                ]);
                        });
                    }
                }
            }catch (\Throwable $throwable){
                $tool->clearFile($path);
                continue;
            }
            Logger::getInstance()->info("签名完成 app_id===".$data["id"]);
            $num++;
            $tool->clearFile($path);
        }
        Logger::getInstance()->info("异步任务==".$taskId."===签名完成，总共签名==".$num."===".date("Y-m-d H:i:s")."===");
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

}