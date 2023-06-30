<?php


namespace App\Task;


use App\Lib\GoogleOss;
use App\Lib\Oss;
use App\Lib\RedisLib;
use App\Lib\Tool;
use App\Mode\App;
use App\Mode\OssConfig;
use App\Mode\ProxyUserDomain;
use App\Mode\User;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class AutoOssToGoogle implements TaskInterface
{
    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
        Logger::getInstance()->error("同步错误错误:" . $throwable->getMessage());
    }

    public function run(int $taskId, int $workerIndex)
    {
        $list = RedisPool::invoke(function (Redis $redis){
            $redis->select(11);
            return $redis->sMembers("oss_to_google");
        });
        if(empty($list)){
            return true;
        }
        $url = "http://35.227.214.161/index/oss_to_google";
        $oss_config = Config::getInstance()->getConf("G_OSS");
        $oss = new Oss($oss_config);
        $google_private_oss_config = Config::getInstance()->getConf("Google_PRIVATE_OSS");
        $google_oss = new GoogleOss($google_private_oss_config);

        foreach ($list as &$v){
            $data = json_decode($v,true);
            $time = time()-$data["start_time"];
            /**40秒内或者10分钟内未同步的**/
            if($time<10){
                continue;
            }
            if($time>1200){
                RedisPool::invoke(function (Redis $redis) use ($v) {
                    $redis->select(11);
                    return $redis->sRem("oss_to_google", $v);
                });
                $time=null;
                $data=null;
                continue;
            }
            $app_id = $data["app_id"];
            /**谷歌上传**/
            if(isset($data["type"])&&$data["type"]==20){
                if($oss->is_exit($data["oss_path"])){
                    DbManager::getInstance()->invoke(function ($client) use ($data) {
                        App::invoke($client)->where("id", $data["app_id"])
                            ->update([
                                "account_id" => $data["account_id"],
                                "oss_path" => $data["oss_path"],
                                'package_name' => $data['package_name'],
                                "update_time" => date("Y-m-d H:i:s")
                            ]);
                    });
                    RedisPool::invoke(function (Redis $redis) use ($v) {
                        $redis->select(11);
                        return $redis->sRem("oss_to_google", $v);
                    });
                    RedisLib::del("sign_app_loading:".$app_id,8);
                }else{
                    /**OSS分流***/
                    $async_oss_config =  DbManager::getInstance()->invoke(function ($client)use ($app_id){
                        $app = App::invoke($client)->where("id", $app_id)
                            ->where("is_delete", 1)
                            ->get();
                        if($app){
                            $user = User::invoke($client)->where('id', $app["user_id"])
                                ->where("status", "normal")
                                ->get();
                            if($user){
                                $data = ProxyUserDomain::invoke($client)->where('user_id', $user["pid"])->get();
                                if($data["oss_id"]){
                                    $async_oss_config = OssConfig::invoke($client)->where("id",$data["oss_id"])->get();
                                    if(!empty($async_oss_config)){
                                        return  $async_oss_config->toArray();
                                    }
                                }
                            }
                        }
                       return null;
                    });
                    if($async_oss_config){
                        $oss_id = $async_oss_config["id"];
                    }else{
                        $oss_id =0;
                    }
                    /**
                     * @todo 谷歌同步
                     */
                    $tool = new Tool();
                    $post_google = [
                        "oss_path" => $data["oss_path"],
                        "sign" => strtoupper(md5($data["oss_path"] . "kiopmwhyusn")),
                        "oss_id"=>$oss_id,
                        "async_oss_config"=>$async_oss_config,
                    ];
                    $result = $tool->http_client("http://35.227.214.161/index/google_to_oss", $post_google);
                }
            }else {
                /**同步成功**/
                if ($google_oss->exists($data["oss_path"])) {
                    DbManager::getInstance()->invoke(function ($client) use ($data) {
                        App::invoke($client)->where("id", $data["app_id"])
                            ->update([
                                "account_id" => $data["account_id"],
                                "oss_path" => $data["oss_path"],
                                'package_name' => $data['package_name'],
                                "update_time" => date("Y-m-d H:i:s")
                            ]);
                    });
                    RedisPool::invoke(function (Redis $redis) use ($v) {
                        $redis->select(11);
                        return $redis->sRem("oss_to_google", $v);
                    });
                    RedisLib::del("sign_app_loading:".$app_id,8);
                    Logger::getInstance()->info("谷歌包存在：".$app_id."==== ".$data["oss_path"]);
                } else {
                    Logger::getInstance()->error("谷歌包不存在：".$app_id."==== ".$data["oss_path"]);
                    /**OSS分流***/
                    $async_oss_config =  DbManager::getInstance()->invoke(function ($client)use ($app_id){
                        $app = App::invoke($client)->where("id", $app_id)
                            ->where("is_delete", 1)
                            ->get();
                        if($app){
                            $user = User::invoke($client)->where('id', $app["user_id"])
                                ->where("status", "normal")
                                ->get();
                            if($user){
                                $data = ProxyUserDomain::invoke($client)->where('user_id', $user["pid"])->get();
                                if($data["oss_id"]){
                                    $async_oss_config = OssConfig::invoke($client)->where("id",$data["oss_id"])->get();
                                    if(!empty($async_oss_config)){
                                        return  $async_oss_config->toArray();
                                    }
                                }
                            }
                        }
                        return null;
                    });
                    if($async_oss_config){
                        $oss_id = $async_oss_config["id"];
                    }else{
                        $oss_id =0;
                    }
                    /**
                     * @todo 谷歌同步
                     */
                    $post_google = [
                        "oss_path" => $data["oss_path"],
                        "sign" => strtoupper(md5($data["oss_path"] . "kiopmwhyusn")),
                        "oss_id"=>$oss_id,
                        "async_oss_config"=>$async_oss_config,
                    ];
                    $result = null;
                    $result = $this->http_client($url, $post_google);
                    $status_code = $result->getStatusCode();
                }
            }
        }
        /**安卓**/
        $apk_list = RedisPool::invoke(function (Redis $redis){
            $redis->select(11);
            return $redis->sMembers("async_apk");
        });
        if(empty($apk_list)){
            $apk_list=null;
            return true;
        }
        foreach ($apk_list as &$v){
            $data = json_decode($v,true);
            $time = time()-$data["start_time"];
            /**40秒内或者10分钟内未同步的**/
            if($time<30){
                $data=null;
                $time=null;
                continue;
            }
            if($time>600){
                RedisPool::invoke(function (Redis $redis) use (&$v) {
                    $redis->select(11);
                    return $redis->sRem("async_apk", $v);
                });
                $data=null;
                $time=null;
                continue;
            }
            $app_id = $data["app_id"];
            /**谷歌上传**/
            if(isset($data["type"])&&$data["type"]==20){
//                $oss_config = Config::getInstance()->getConf("G_OSS");
//                $oss = new Oss($oss_config);
                if($oss->is_exit($data["oss_path"])){
                    RedisPool::invoke(function (Redis $redis) use ($v) {
                        $redis->select(11);
                        return $redis->sRem("async_apk", $v);
                    });
                }else{
                    /**OSS分流***/
                    $async_oss_config =  DbManager::getInstance()->invoke(function ($client)use ($app_id){
                        $app = App::invoke($client)->where("id", $app_id)
                            ->where("is_delete", 1)
                            ->get();
                        if($app){
                            $user = User::invoke($client)->where('id', $app["user_id"])
                                ->where("status", "normal")
                                ->get();
                            if($user){
                                $data = ProxyUserDomain::invoke($client)->where('user_id', $user["pid"])->get();
                                if($data["oss_id"]){
                                    $async_oss_config = OssConfig::invoke($client)->where("id",$data["oss_id"])->get();
                                    if(!empty($async_oss_config)){
                                        return  $async_oss_config->toArray();
                                    }
                                }
                            }
                        }
                        return null;
                    });
                    if($async_oss_config){
                        $oss_id = $async_oss_config["id"];
                    }else{
                        $oss_id =0;
                    }
                    $post_google = [
                        "oss_path"=>$data["oss_path"],
                        "sign"=>strtoupper(md5($data["oss_path"]."kiopmwhyusn")),
                        "oss_id"=>$oss_id,
                        "async_oss_config"=>$async_oss_config,
                    ];
                    $result = $this->http_client("http://35.227.214.161/index/google_to_oss",$post_google);
                }
            }else{
//                $google_private_oss_config = Config::getInstance()->getConf("Google_PRIVATE_OSS");
//                $google_oss = new GoogleOss($google_private_oss_config);
                /**同步成功**/
                if ($google_oss->exists($data["oss_path"])) {
                    RedisPool::invoke(function (Redis $redis) use ($v) {
                        $redis->select(11);
                        return $redis->sRem("async_apk", $v);
                    });
                }else{
                    /**OSS分流***/
                    $async_oss_config =  DbManager::getInstance()->invoke(function ($client)use ($app_id){
                        $app = App::invoke($client)->where("id", $app_id)
                            ->where("is_delete", 1)
                            ->get();
                        if($app){
                            $user = User::invoke($client)->where('id', $app["user_id"])
                                ->where("status", "normal")
                                ->get();
                            if($user){
                                $data = ProxyUserDomain::invoke($client)->where('user_id', $user["pid"])->get();
                                if($data["oss_id"]){
                                    $async_oss_config = OssConfig::invoke($client)->where("id",$data["oss_id"])->get();
                                    if(!empty($async_oss_config)){
                                        return  $async_oss_config->toArray();
                                    }
                                }
                            }
                        }
                        return null;
                    });
                    if($async_oss_config){
                        $oss_id = $async_oss_config["id"];
                    }else{
                        $oss_id =0;
                    }
                    $post_google = [
                        "oss_path"=>$data["oss_path"],
                        "sign"=>strtoupper(md5($data["oss_path"]."kiopmwhyusn")),
                        "oss_id"=>$oss_id,
                        "async_oss_config"=>$async_oss_config,
                    ];
                    $result = $this->http_client("http://35.227.214.161/index/oss_to_google",$post_google);
                }
            }
        }
        return true;
    }

    function http_client($url, $data = [])
    {
        $client = new HttpClient();
        $client->setUrl($url);
        $client->setTimeout(30);
        if (!empty($data)) {
            $result = $client->post($data);
        } else {
            $result = $client->get();
        }
        return $result;
    }

}