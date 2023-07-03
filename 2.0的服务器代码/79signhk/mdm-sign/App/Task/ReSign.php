<?php


namespace App\Task;


use App\Lib\Oss;
use App\Lib\PropertyList;
use App\Lib\Tool;
use CFPropertyList\CFPropertyList;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use Throwable;

class ReSign implements TaskInterface
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
        $dllib = ROOT_PATH."/other/check_resign_dlib/libswiftSqllite3.framework";
        $path = ROOT_PATH . "/cache/signapp/" . uniqid() . '/';
        if (empty($this->taskData["app_id"]) || empty($this->taskData["udid"])) {
            return true;
        }
        $start_time = time();
        $app_id = $this->taskData["app_id"];
        $udid = $this->taskData["udid"];
        $append_data = $this->taskData["append_data"];
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $data = $this->taskData;
        $api_url = Config::getInstance()->getConf('API_URL');
        $oss = new Oss();
        $tool = new Tool();
        $bot_url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
        try {
            $password = $data["password"];
            if (!$oss->ossDownload($data["app_path"], $path . "cache.ipa")) {
                Logger::getInstance()->error("包下载失败");
                return false;
            }
            if (!$oss->ossDownload($data["cert_path"], $path . "ios.p12")) {
                Logger::getInstance()->error("证书下载失败");
                return false;
            }
            if (!$oss->ossDownload($data["oss_provisioning"], $path . "ios.mobileprovision")) {
                Logger::getInstance()->error("描述文件下载失败");
                return false;
            }
            if (!is_file($path . "cache.ipa")) {
                Logger::getInstance()->error("签名包不存在");
                return false;
            }
            exec("$ausign --email kiohhu@qq.com -p 123456");
            /***开心签注入****/
            $log = $status = null;
            exec("cd $path && $ausign --llib cache.ipa", $log, $status);
            /***删除以前注入**/
            if ($status == 0) {
                $result = json_decode(implode('', $log), true);
                if ($result['status'] == 1 && !empty($result['message'])) {
                    $llib = $result['message'];
                    foreach ($llib as $k => $v) {
                        if ($v['name'] == '@rpath/AliyunFYDunSDK.framework/AliyunFYDunSDK' || $v['name'] == '@rpath/AliyunFangYuDunSDK.framework/AliyunFangYuDunSDK') {
                            exec("cd $path && $ausign --dlib cache.ipa -i '@rpath/AliyunFYDunSDK.framework/AliyunFYDunSDK##" . $v['file'] . "'  '@rpath/AliyunFangYuDunSDK.framework/AliyunFangYuDunSDK##" . $v['file']."'");
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
            exec("cd $path && $ausign --alib cache.ipa -i $dllib");
            exec("cd $path && unzip -O gb2312 cache.ipa");
            $tmp_dir_list = scandir($path . 'Payload');
            $app_folder = "";
            $app_name = "";
            foreach ($tmp_dir_list as $k => $v) {
                if ($v != '.' && $v != '..' && is_dir($path . 'Payload/' . $v) && strstr($v, '.app')) {
                    $app_folder = $path . 'Payload/' . $v . "/Info.plist";
                    $app_name = $v;
                    break;
                }
            }
            if (empty($app_folder) || !is_file($app_folder)) {
                Logger::getInstance()->error("签名失败,未找到Plist文件");
                return false;
            }
            /**改库不签名**/
            if(is_dir($path. '/Payload/' . $app_name."/Frameworks")){
                $is_zx =  scandir($path. '/Payload/' . $app_name."/Frameworks");
                foreach ($is_zx as $v) {
                    if ($v != '.' && $v != '..' && $v == "AliyunFangYuDunSDK.framework" && is_dir($path . '/Payload/' . $app_name . "/Frameworks/AliyunFangYuDunSDK.framework")) {
                        $tool->clearFile($path . '/Payload/' . $app_name . "/Frameworks/AliyunFangYuDunSDK.framework");
                    }
                }
            }
            // 获取plist文件内容
            $content = file_get_contents($app_folder);
            // 解析plist成数组
            $ipa = new CFPropertyList();
            $ipa->parse($content);
            $ipaInfo = $ipa->toArray();
            if (isset($ipaInfo['AliyunTag'])) {
                unset($ipaInfo['AliyunTag']);
            }
            $ipaInfo["AliyunTag"] = $data["tag"];
            $ipaInfo["UDID"] = $udid;

            $ipaInfo["kevstoreidvf"] = $data["kevstoreidvf"];
            if (!empty($append_data)) {
                $append_data = json_decode($append_data, true);
                foreach ($append_data as $k => $v) {
                    if (in_array(strtolower($k), ["udid", "aliyuntag"])) {
                        continue;
                    }
                    $ipaInfo[$k] = $v;
                }
            }
            $obj = $tool->array_no_empty($ipaInfo);
            $plist = new PropertyList($obj);
            $xml = $plist->xml();
            file_put_contents($app_folder, $xml);
            /**去除APP文件夹名称特殊字符***/
            $cache_app_name = $tool->strspacedel($app_name);
            if (mb_strlen($app_name) != mb_strlen($cache_app_name)) {
                $log = $status = null;
                exec("cd $path/Payload && mv $app_name  $cache_app_name", $log, $status);
                if ($status !== 0) {
                    exec("cd $path/Payload && mv *.app  $cache_app_name");
                }
            }

            exec("cd $path && zip -q -r -1 dailb.ipa  Payload");
            if (empty($password)) {
                $shell = "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p \"\" -o sign.ipa -z 1 dailb.ipa";
            } else {
                $shell = "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p $password -o sign.ipa -z 1 dailb.ipa";
            }
            $log = $status = null;
            exec($shell, $log, $status);
            if (is_file($path . "sign.ipa")) {
                $oss_save = 'signapp/' . date("Ymd") . "/" . $data["tag"] . '/' . $udid . '.ipa';
                    $public_oss_config = Config::getInstance()->getConf('PUBLIC_G_OSS');
                    $public_oss = new Oss($public_oss_config);
                    if ($public_oss->ossUpload($path . "sign.ipa", $oss_save)) {
                        $oss_save_url = $public_oss_config["url"] . $oss_save;
                    } else {
                        if ($public_oss->ossUpload($path . "sign.ipa", $oss_save)) {
                            $oss_save_url = $public_oss_config["url"] . $oss_save;
                        } else {
                            Logger::getInstance()->error("签名上传失败");
                            $post_data = [
                                "chat_id" => "-1001463689548",
                                "text" =>"重签注入签名上传失败，TAG: ".$data["tag"].",UDID: $udid",
                            ];
                            $result = $this->http_client($bot_url, $post_data);
                            $tool->clearFile($path);
                            return true;
                        }
                    }
                $sign_data = [
                    "oss_path" => $oss_save_url,
                    "tag" => $data["tag"],
                    "is_overseas" => 20,
                    "udid" => $udid,
                ];
                $key_url = $api_url."index/key_redis";
                $get_result = $this->http_client($key_url, $sign_data);
                $statusCode = $get_result->getStatusCode();
                $end_time = time()-$start_time;
                Logger::getInstance()->info("签名成功===" . $statusCode . "===$udid==$app_id===耗时： $end_time 秒 =");
                if($statusCode!=200){
                    \co::sleep(5);
                    $get_result = $this->http_client($key_url, $sign_data);
                    $statusCode = $get_result->getStatusCode();
                    if($statusCode!=200){
                        $post_data = [
                            "chat_id" => "-1001463689548",
                            "text" =>"重签回传数据接口调用失败，TAG: ".$data["tag"].",UDID: $udid, statusCode: $statusCode",
                        ];
                        $result = $this->http_client($bot_url, $post_data);
                    }
                }
            } else {
                Logger::getInstance()->error("签名失败" . $shell);
                $post_data = [
                    "chat_id" => "-1001463689548",
                    "text" =>"重签注入签名失败，TAG: ".$data["tag"].",UDID: $udid",
                ];
                $result = $this->http_client($bot_url, $post_data);
            }
            $tool->clearFile($path);
            return true;
        } catch (\Throwable $exception) {
            $post_data = [
                "chat_id" => "-1001463689548",
                "text" =>"重签注入签名失败，APP_id: ".$app_id.",UDID: $udid ; ",
            ];
            $result = $this->http_client($bot_url, $post_data);
            Logger::getInstance()->error("重签错误1：".json_encode($exception->getMessage()));
        }
        $tool->clearFile($path);
        return true;
    }

    public function onException(Throwable $throwable, int $taskId, int $workerIndex)
    {
        Logger::getInstance()->error("重签错误2：".json_encode($throwable->getMessage()));
    }

    public function http_client($url, $data = [], $header = [])
    {
        $client = new HttpClient();
        $client->setUrl($url);
        $client->setTimeout(-1);
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