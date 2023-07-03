<?php


namespace App\Task;


use App\Lib\Oss;
use App\Lib\PropertyList;
use App\Lib\Tool;
use CFPropertyList\CFPropertyList;
use co;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use Throwable;

class SignV1App implements TaskInterface
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
        $path = ROOT_PATH . "/cache/signapp/" . uniqid() . '/';
        if (empty($this->taskData["app_id"]) || empty($this->taskData["udid"])) {
            return true;
        }
//        $app_id = $this->taskData["app_id"];
        $udid = $this->taskData["udid"];
//        $append_data = $this->taskData["extend"];
        $data = $this->taskData;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
//        $api_url = Config::getInstance()->getConf('API_URL');
        $key_url = "http://35.220.140.22:85/index/sign_app_after";
        $oss = new Oss();
        $tool = new Tool();
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
            if (!$oss->ossDownload($data["provisioning"], $path . "ios.mobileprovision")) {
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
                        if ($v['name'] == '@rpath/AliyunFYDunSDK.framework/AliyunFYDunSDK') {
                            exec("cd $path && $ausign --dlib cache.ipa -i '@rpath/AliyunFYDunSDK.framework/AliyunFYDunSDK##" . $v['file'] . "'");
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
            exec("cd $path && unzip -O gb2312 cache.ipa");
            $tmp_dir_list = scandir($path . 'Payload');
            $app_folder = "";
            $app_name = "";
            foreach ($tmp_dir_list as $k => $v) {
                if ($v != '.' && $v != '..' && is_dir($path . 'Payload/' . $v) && strstr($v, '.app')) {
                    $app_folder = $path . 'Payload/' . $v . "/Info.plist";
                    $app_name=$v;
                    break;
                }
            }
            if (empty($app_folder) || !is_file($app_folder)) {
                Logger::getInstance()->error("签名失败,未找到Plist文件");
                return false;
            }
            // 获取plist文件内容
            $content = file_get_contents($app_folder);
            // 解析plist成数组
            $ipa = new CFPropertyList();
            $ipa->parse($content);
            $ipaInfo = $ipa->toArray();
//            if (isset($ipaInfo['AliyunTag'])) {
//                unset($ipaInfo['AliyunTag']);
//            }
//            $ipaInfo["AliyunTag"] = $data["tag"];
//            $ipaInfo["UDID"] = $udid;
            if(!empty($append_data)){
                $append_data = json_decode($append_data,true);
                foreach ($append_data as $k=>$v){
                    if(in_array(strtolower($k),["udid","aliyuntag"])){
                        continue;
                    }
                    $ipaInfo[$k]=$v;
                }
            }

            $obj = $tool->array_no_empty($ipaInfo);
            $plist = new PropertyList($obj);
            $xml = $plist->xml();
            file_put_contents($app_folder, $xml);
            /**去除APP文件夹名称特殊字符***/
            $cache_app_name = $tool->strspacedel($app_name);
            if(mb_strlen($app_name)!=mb_strlen($cache_app_name)){
                $log = $status = null;
                exec("cd $path/Payload && mv $app_name  $cache_app_name",$log,$status);
                if($status!==0){
                    exec("cd $path/Payload && mv *.app  $cache_app_name");
                }
            }

            exec("cd $path && zip -q -r -1  dailb.ipa  Payload");
            if (empty($password)) {
                $shell = "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p \"\" -o sign.ipa -z 1 dailb.ipa";
            } else {
                $shell = "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p $password -o sign.ipa -z 1 dailb.ipa";
            }
            $log = $status = null;
            exec($shell, $log, $status);
            if (is_file($path . "sign.ipa")) {
                $oss_save = 'signapp/' . date("Ymd") . "/" . $data["tag"] . '/' . $udid . '.ipa';
                $sign_data = [
                    "oss_path" => $oss_save,
                    "tag" => $data["tag"],
                    "is_overseas" => 10,
                    "udid" => $udid,
                ];

                if ($data["oss_id"] != 0) {
                    if (is_array($data["async_oss_config"])) {
                        $async_oss_config = $data["async_oss_config"];
                    } else {
                        $async_oss_config = json_decode($data["async_oss_config"], true);
                    }
                    $async_oss_config["endpoint"] = 'oss-cn-shenzhen-internal.aliyuncs.com';
                    $async_oss = new Oss($async_oss_config);
                    $oss_upload =  $async_oss->ossUpload($path . "sign.ipa", $oss_save);
                    $sign_data["oss_id"] = $data["oss_id"];
                } else {
                    $oss_upload = $oss->ossUpload($path . "sign.ipa", $oss_save);
                    $sign_data["oss_id"] = 0;
                }
                if($oss_upload==false){
                    Logger::getInstance()->error("包上传失败");
                }
                $get_result = $this->http_client($key_url, $sign_data);
                $statusCode = $get_result->getStatusCode();
                if ($statusCode != 200) {
                    \co::sleep(5);
                    $get_result = $this->http_client($key_url, $sign_data);
                    $statusCode = $get_result->getStatusCode();
                }
                Logger::getInstance()->info("签名成功====$statusCode");
            } else {
                Logger::getInstance()->error("1.0---签名失败");
            }
        } catch (Throwable $exception) {
            var_dump($exception->getMessage());
            Logger::getInstance()->error("1.0签名失败");
        }
        $tool->clearFile($path);
        return true;
    }

    public function onException(Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    function http_client($url, $data = [], $header = [])
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