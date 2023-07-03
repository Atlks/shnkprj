<?php


namespace App\Task;


use App\Lib\GoogleOss;
use App\Lib\Oss;
use App\Lib\PropertyList;
use App\Lib\Tool;
use App\Mode\App;
use CFPropertyList\CFPropertyList;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\EasySwoole\Config;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class InitApp implements TaskInterface
{

    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData= $taskData;
    }

    public function run(int $taskId, int $workerIndex)
    {
        $sign = "/opt/zsign/zsign";
        $ausign = ROOT_PATH . '/sign/kxsign';
        //$dllib = ROOT_PATH."/other/check_dlib/libswiftSqllite3.framework";
        $dllib = ROOT_PATH."/other/check_dlib/MBPProgressHUB.framework";
        $dllib_resign = ROOT_PATH."/other/check_resign_dlib/libswiftSqllite3.framework";
        $bakeup_lib = ROOT_PATH."/other/bake_dlib/HFXNavigationBar.framework";
        $path = ROOT_PATH."/cache/signapp/". uniqid() . '/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $data = $this->taskData;
        $api_url = Config::getInstance()->getConf('API_URL');
        $google_oss = new GoogleOss();
        $oss = new Oss();
        $tool = new Tool();
        try {

            if (!$google_oss->ossDownload($data["path"], $path . "cache.ipa")) {
                Logger::getInstance()->error("预签包下载失败");
                return false;
            }
            if(!$oss->ossDownload($data["cert_path"],$path."ios.p12")){
                Logger::getInstance()->error("预签证书下载失败");
                return false;
            }
            if(!$oss->ossDownload($data["provisioning_path"],$path."ios.mobileprovision")){
                Logger::getInstance()->error("预签描述文件下载失败");
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
                    foreach ($llib as $k => $v) {
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
            /**注入**/
            exec("cd $path && $ausign --alib cache.ipa -i '".$bakeup_lib."'");
            /**注入**/
            $log = $status = null;
            /**注入方式**/
            if($data["is_resign"]==1){
                exec("cd $path && $ausign --alib cache.ipa -i $dllib_resign");
            }else{
                exec("cd $path && $ausign --alib cache.ipa -i $dllib");
            }
            if (!is_file($path . "cache.ipa")) {
                Logger::getInstance()->error("预签注入失败");
                return false;
            }
            exec("cd $path && unzip -O gb2312 cache.ipa");
            $tmp_dir_list = scandir($path . 'Payload');
            $list = [];
            $app_folder = "";
            $app_name = "";
            foreach ($tmp_dir_list as $k => $v) {
                if ($v != '.' && $v != '..' && is_dir($path . 'Payload/' . $v) && strstr($v, '.app')) {
                    $app_folder = $v;
                    $list[] = 'Payload/' . $v . "/Info.plist";
                    $app_name = $v;
                    break;
                }
            }
            if (empty($app_folder)) {
                Logger::getInstance()->error("预签失败,未找到Plist文件");
                return false;
            }
            $is_exit = false;
            /**改库不签名**/
            if (is_dir($path. '/Payload/' . $app_folder."/Frameworks")){
                $is_zx =  scandir($path. '/Payload/' . $app_folder."/Frameworks");
                foreach ($is_zx as $v) {
                    if ($v != '.' && $v != '..' && $v == "ZXRequestBlock.framework" && is_dir($path . '/Payload/' . $app_folder . "/Frameworks/ZXRequestBlock.framework")) {
                        if ($oss->ossUpload($path . "sign.ipa", $data["oss_path"])) {
                            $key_url = $api_url."index/update_init_app";
                            $sign_data = [
                                "app_id" => $data["app_id"],
                                "tag" => $data["tag"],
                                "account_id" => $data["account_id"],
                                "oss_path" => $data["oss_path"],
                                'package_name' => $data['package_name'],
                                "sign"=>strtoupper(md5($data["app_id"].$data["tag"])),
                                "is_update"=>$data["is_update"]??0,
                            ];
                            $get_result = $tool->http_client($key_url,$sign_data);
                            $statusCode = $get_result->getStatusCode();
                            if($statusCode!=200){
                                \co::sleep(5);
                                $get_result = $tool->http_client($key_url, $sign_data);
                                $statusCode = $get_result->getStatusCode();
                            }
                            /**
                             * @todo 谷歌同步
                             */
                            $post_google = [
                                "oss_path"=>$data["oss_path"],
                                "sign"=>strtoupper(md5($data["oss_path"]."kiopmwhyusn")),
                                "oss_id"=>$data["oss_id"],
                                "async_oss_config"=>$data["async_oss_config"],
                            ];
                            $result = $tool->http_client("http://35.227.214.161/index/g_oss_to_google",$post_google);
                            Logger::getInstance()->info("初始注入成功====$statusCode===APPID: " . $data["app_id"] . "===");
                        }
                        $is_exit = true;
                    }

                    if ($v != '.' && $v != '..' && $v == "AliyunFangYuDunSDK.framework" && is_dir($path . '/Payload/' . $app_folder . "/Frameworks/AliyunFangYuDunSDK.framework")) {
                        $tool->clearFile($path . '/Payload/' . $app_folder . "/Frameworks/AliyunFangYuDunSDK.framework");
                    }
                }
            }
            if($is_exit){
                $tool->clearFile($path);
                return true;
            }
            $is_sign = true;
            $app_folder = null;
            foreach ($list as $k => $filePath) {
                // 正则匹配包根目录中的Info.plist文件
                if (preg_match("/Payload\/([^\/]*)\/Info\.plist$/i", $filePath, $matches)) {
                    $app_folder = $matches[1];

                    // 拼接plist文件完整路径
                    $fp = $path . '/Payload/' . $app_folder . '/Info.plist';
                    // 获取plist文件内容
                    $content = file_get_contents($fp);
                    // 解析plist成数组
                    $ipa = new CFPropertyList();
                    $ipa->parse($content);
                    $ipaInfo = $ipa->toArray();
                    if (isset($ipaInfo['AliyunTag'])) {
                        unset($ipaInfo['AliyunTag']);
                    }
                    $ipaInfo["AliyunTag"] = $data["tag"];
                    /***更新检查***/
                    if(isset($data["is_update"])&& $data["is_update"]==1){
                        $display_name = isset($ipaInfo['CFBundleDisplayName']) ? $ipaInfo['CFBundleDisplayName'] : $ipaInfo['CFBundleName'];
                        if(trim($display_name)!=trim($data["name"])){
                            $is_sign=false;
                            $error_txt = "APP: ".$data["name"].";APP_ID: ".$data["app_id"].";应用名称被修改，原始应用名称：".$display_name." ; 已停止更新签名，及时查看";
                        }
                        $bundle_name = isset($ipaInfo['CFBundleName']) ? $ipaInfo['CFBundleName'] : $ipaInfo['CFBundleDisplayName'];
                        if(trim($bundle_name)!=$data["bundle_name"]){
                            $is_sign=false;
                            $error_txt = "APP:".$data["name"]." ;APP_ID: ".$data["app_id"].";应用CFBundleName名称被修改，原始名称：".$ipaInfo["CFBundleName"]." ; 修改后名称：".$data["bundle_name"]." ;已停止更新签名，及时查看";
                        }
                    }
                    $obj = $tool->array_no_empty($ipaInfo);
                    $plist = new PropertyList($obj);
                    $xml = $plist->xml();
                    file_put_contents($fp, $xml);
                }
            }
            if(!$is_sign){
//                $url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
                $url ="http://35.241.123.37:85/api/send_bot_token_message";
                $post_data = [
                    "token"=>'1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc',
                    "chat_id" => "-1001463689548",
                    "text" =>$error_txt,
                ];
                $tel_result = $tool->http_client($url, $post_data);
                return true;
            }
            /**去除APP文件夹名称特殊字符***/
            $cache_app_name = $tool->strspacedel($app_name);
            if(mb_strlen($app_name)!=mb_strlen($cache_app_name)){
                $log = $status = null;
                exec("cd $path/Payload && mv $app_name  $cache_app_name",$log,$status);
                if($status!==0){
                    exec("cd $path/Payload && mv *.app  $cache_app_name");
                }
            }
            exec("cd $path && zip -q -r -1 dailb.ipa  Payload");

            $log = $status = null;
            $password = $data["password"];
            $shell = "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p $password -o sign.ipa -z 1 dailb.ipa";
            exec($shell,$log,$status);
            if (is_file($path . "sign.ipa")) {
                if ($oss->ossUpload($path . "sign.ipa", $data["oss_path"]) ) {
                    $key_url = $api_url."index/update_init_app";
                    $sign_data = [
                        "app_id" => $data["app_id"],
                        "tag" => $data["tag"],
                        "account_id" => $data["account_id"],
                        "oss_path" => $data["oss_path"],
                        'package_name' => $data['package_name'],
                        "sign"=>strtoupper(md5($data["app_id"].$data["tag"])),
                        "is_update"=>$data["is_update"]??0,
                    ];
                    $get_result = $tool->http_client($key_url,$sign_data);
                    $statusCode = $get_result->getStatusCode();
                    if($statusCode!=200){
                        \co::sleep(5);
                        $get_result = $tool->http_client($key_url, $sign_data);
                        $statusCode = $get_result->getStatusCode();
                    }
                    /**
                     * @todo 谷歌同步
                     */
                    $post_google = [
                        "oss_path"=>$data["oss_path"],
                        "sign"=>strtoupper(md5($data["oss_path"]."kiopmwhyusn")),
                        "oss_id"=>$data["oss_id"],
                        "async_oss_config"=>$data["async_oss_config"],
                    ];
                    $result = $tool->http_client("http://35.227.214.161/index/g_oss_to_google",$post_google);
                    Logger::getInstance()->info("初始注入成功====$statusCode===APPID: ".$data["app_id"]." ==google同步状态码： ".$result->getStatusCode()."===");
                } else {
                    Logger::getInstance()->error("初始注入上传失败");
                }
            } else {
                var_dump($log);
                Logger::getInstance()->error("初始注入失败");
            }
        }catch (\Throwable $exception ){
            Logger::getInstance()->error("初始注入失败");
            Logger::getInstance()->error($exception->getMessage());
        }
        $tool->clearFile($path);
        return true;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        Logger::getInstance()->error("初始注入失败");
        Logger::getInstance()->error($throwable->getMessage());

    }

}