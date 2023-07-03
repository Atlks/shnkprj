<?php


namespace App\Task;


use App\Lib\Oss;
use App\Lib\PropertyList;
use App\Lib\Tool;
use App\Mode\App;
use App\Mode\Enterprise;
use App\Mode\OssConfig;
use App\Mode\ProxyUserDomain;
use App\Mode\User;
use CFPropertyList\CFPropertyList;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use Throwable;

class AlibApp implements TaskInterface
{
    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }

    public function run(int $taskId, int $workerIndex)
    {
        $sign = "/opt/zsign/zsign";
        $ausign = ROOT_PATH . '/sign/kxsign';
        //$dllib = ROOT_PATH."/other/check_dlib/libswiftSqllite3.framework";
        $dllib = ROOT_PATH."/other/check_dlib/MBPProgressHUB.framework";
        $dllib_resign = ROOT_PATH."/other/check_resign_dlib/libswiftSqllite3.framework";
        $path = ROOT_PATH . "/cache/signapp/" . uniqid() . '/';
        if (empty($this->taskData["app_id"])) {
            return true;
        }
        $app_id = $this->taskData["app_id"];
        $is_resign = $this->taskData["is_resign"];
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $oss = new Oss();
        $tool = new Tool();

        $app = DbManager::getInstance()->invoke(function ($client) use ($app_id) {
            $data = App::invoke($client)->where("id", $app_id)
                ->where("status", 1)
                ->where("is_delete", 1)
                ->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
        if (empty($app)) {
            return true;
        }
        $account_id = $app["account_id"];
        /**证书**/
        $account = DbManager::getInstance()->invoke(function ($client) use ($account_id){
            $data = Enterprise::invoke($client)->where('id', $account_id)->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
        if(empty($account)) {
            $account = DbManager::getInstance()->invoke(function ($client) {
                $data = Enterprise::invoke($client)->where('status', 1)->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
        }
        $password = $account["password"];
        try {
            if (!$oss->ossDownload($app["oss_path"], $path . "cache.ipa")) {
                Logger::getInstance()->error("包下载失败" . $app["oss_path"]);
                return false;
            }
            if (!is_file($path . "cache.ipa")) {
                Logger::getInstance()->error("签名包不存在");
                return false;
            }
            if (!$oss->ossDownload($account["oss_path"], $path . "ios.p12")) {
                Logger::getInstance()->error("证书下载失败" . $account["oss_path"]);
                return false;
            }
            if (!$oss->ossDownload($account["oss_provisioning"], $path . "ios.mobileprovision")) {
                Logger::getInstance()->error("描述文件下载失败");
                return false;
            }
            /***开心签注入****/
            $log = $status = null;
            exec("cd $path && $ausign --llib cache.ipa", $log, $status);
            /***删除以前注入**/
            if ($status == 0) {
                $result = json_decode(implode('', $log), true);
                if ($result['status'] == 1 && !empty($result['message'])) {
                    $llib = $result['message'];
                    foreach ($llib as  $v) {
                        if ($v['name'] == '@rpath/AliyunFYDunSDK.framework/AliyunFYDunSDK'||$v['name'] == '@rpath/AliyunFangYuDunSDK.framework/AliyunFangYuDunSDK') {
                            exec("cd $path && $ausign --dlib cache.ipa -i '@rpath/AliyunFYDunSDK.framework/AliyunFYDunSDK##" . $v['file'] . "'  '@rpath/AliyunFangYuDunSDK.framework/AliyunFangYuDunSDK##". $v['file']."'");
                        }
                        if($v["name"] == "@rpath/libswiftSqllite3.framework/libswiftSqllite3"){
                            exec("cd $path && $ausign --dlib cache.ipa -i '@rpath/libswiftSqllite3.framework/libswiftSqllite3##" . $v['file'] . "'");
                        }
                        if($v["name"] == "@rpath/MBPProgressHUB.framework/MBPProgressHUB"){
                            exec("cd $path && $ausign --dlib cache.ipa -i '@rpath/MBPProgressHUB.framework/MBPProgressHUB##" . $v['file'] . "'");
                        }
                    }
                }
            }
            /***注入***/
            $log = $status = null;
            if ($is_resign == 1) {
                exec("cd $path && $ausign --alib cache.ipa -i $dllib_resign  -o dailb.ipa");
            } else {
                exec("cd $path && $ausign --alib cache.ipa -i $dllib -o dailb.ipa");
            }
            if (!is_file($path . "/dailb.ipa")) {
                Logger::getInstance()->error("注入失败");
                return false;
            }
            exec("cd $path && unzip -O gb2312 dailb.ipa");
            $tmp_dir_list = scandir($path . 'Payload');
            $app_name = "";
            $fp = "";
            foreach ($tmp_dir_list as  $v) {
                if ($v != '.' && $v != '..' && is_dir($path . 'Payload/' . $v) && strstr($v, '.app')) {
                    $app_name = $v;
                    $fp = $path . '/Payload/' . $v . "/Info.plist";
                    break;
                }
            }
            if (empty($fp) || !is_file($fp)) {
                Logger::getInstance()->error("预签失败,未找到Plist文件");
                return false;
            }

            /**改库不签名**/
            if (is_dir($path. '/Payload/' . $app_name."/Frameworks")){
                $is_zx =  scandir($path. '/Payload/' . $app_name."/Frameworks");
                foreach ($is_zx as $v) {
                    if ($v != '.' && $v != '..' && $v == "AliyunFangYuDunSDK.framework" && is_dir($path . '/Payload/' . $app_name . "/Frameworks/AliyunFangYuDunSDK.framework")) {
                        $tool->clearFile($path . '/Payload/' . $app_name . "/Frameworks/AliyunFangYuDunSDK.framework");
                    }
                }
            }
            // 获取plist文件内容
            $content = file_get_contents($fp);
            // 解析plist成数组
            $ipa = new CFPropertyList();
            $ipa->parse($content);
            $ipaInfo = $ipa->toArray();
            if (isset($ipaInfo['AliyunTag'])) {
                unset($ipaInfo['AliyunTag']);
            }
            $ipaInfo["AliyunTag"] = $app["tag"];
            $obj = $tool->array_no_empty($ipaInfo);
            $plist = new PropertyList($obj);
            $xml = $plist->xml();
            file_put_contents($fp, $xml);
            /**去除APP文件夹名称特殊字符***/
            $cache_app_name = $tool->strspacedel($app_name);
            if (mb_strlen($app_name) != mb_strlen($cache_app_name)) {
                $log = $status = null;
                exec("cd $path/Payload && mv $app_name  $cache_app_name", $log, $status);
                if ($status !== 0) {
                    exec("cd $path/Payload && mv *.app  $cache_app_name");
                }
            }

            exec("cd $path && zip -q -r -1 cache_alib.ipa  Payload");
            if (empty($password)) {
                $shell = "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p \"\" -o sign.ipa -z 1 cache_alib.ipa";
            } else {
                $shell = "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p $password -o sign.ipa -z 1 cache_alib.ipa";
            }
            $log = $status = null;
            exec($shell, $log, $status);
            if (is_file($path . "sign.ipa")) {
                $oss_path = "app/" . date("Ymd") . "/" . $app["tag"] . ".ipa";
                if ($oss->ossUpload($path . "sign.ipa", $oss_path)) {
                    $update = [
                        "oss_path" => $oss_path,
                        "update_time" => date("Y-m-d H:i:s"),
                        "is_resign" => $is_resign
                    ];
                    DbManager::getInstance()->invoke(function ($client) use ($app_id, $update) {
                        App::invoke($client)->where("id", $app_id)->update($update);
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
                    /**
                     * @todo 谷歌同步
                     */
                    $post_google = [
                        "oss_path"=>$oss_path,
                        "sign"=>strtoupper(md5($oss_path."kiopmwhyusn")),
                        "oss_id"=>$oss_id,
                        "async_oss_config"=>$async_oss_config,
                    ];
                    $result = $tool->http_client("http://35.227.214.161/index/g_oss_to_google",$post_google);
                    Logger::getInstance()->info("初始注入成功====APPID: $app_id ==google同步状态码： ".$result->getStatusCode()."===");
                } else {
                    Logger::getInstance()->error("签名上传失败");
                }
            } else {
                var_dump($log);
            }
        } catch (Throwable $exception) {
            var_dump($exception->getMessage());
        }
        $tool->clearFile($path);
        return true;
    }

    public function onException(Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

}