<?php


namespace App\HttpController;


use App\Lib\IosPackage;
use App\Lib\Oss;
use App\Lib\Tool;
use App\Mode\App;
use App\Mode\BaleRate;
use App\Mode\OssConfig;
use App\Mode\User;
use App\Task\InitApp;
use App\Task\ReSign;
use App\Task\SignTask;
use App\Task\SignV1App;
use App\Task\UploadZDOss;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\ORM\DbManager;

class Index extends Controller
{


    protected function actionNotFound(?string $action)
    {
        $this->response()->withStatus(404);
        $file = EASYSWOOLE_ROOT . '/vendor/easyswoole/easyswoole/src/Resource/Http/404.html';
        if (!is_file($file)) {
            $file = EASYSWOOLE_ROOT . '/src/Resource/Http/404.html';
        }
        $this->response()->write(file_get_contents($file));
    }

    public function index()
    {
        $this->response()->withStatus(200);
        $file = EASYSWOOLE_ROOT . '/vendor/easyswoole/easyswoole/src/Resource/Http/404.html';
        if (!is_file($file)) {
            $file = EASYSWOOLE_ROOT . '/src/Resource/Http/404.html';
        }
        $this->response()->write(file_get_contents($file));
    }

    public function ipaParsing()
    {
        $path = $this->request()->getRequestParam('oss_path');
        $user_id = $this->request()->getRequestParam('user_id');
        $ios = new IosPackage();
        $appInfo = $ios->getIosPackage($path,$user_id);
        if (!empty($appInfo["icon"])) {
            $oss_config = Config::getInstance()->getConf('G_OSS');
            $oss = new Oss($oss_config);
            Logger::getInstance()->info($appInfo["icon"]);
            $appInfo["icon_url"] = $oss->signUrl($appInfo["icon"]);
        }
        $this->writeJson(200, $appInfo);
    }

    public function ipa_admin_parsing()
    {
        $path = $this->request()->getRequestParam('oss_path');
        $user_id = $this->request()->getRequestParam('user_id');
        $ios = new IosPackage();
        $appInfo = $ios->getAdminIosPackage($path,$user_id);
        if (!empty($appInfo["icon"])) {
            $oss_config = Config::getInstance()->getConf('G_OSS');
            $oss = new Oss($oss_config);
            $appInfo["icon_url"] = $oss->signUrl($appInfo["icon"]);
        }
        $this->writeJson(200, $appInfo);
    }


    /**
     * APK同步
     */
    public function apkoss()
    {
        $path = $this->request()->getRequestParam('path');
        $oss_path = $this->request()->getRequestParam('oss_path');
        if (empty($path) || empty($oss_path)) {
            return $this->writeJson(200, ["code" => 0]);
        }
        $oss = new Oss();
        $save_path = ROOT_PATH . "/cache/apk/" . uniqid();
        if (!is_dir($save_path)) {
            mkdir($save_path, 0777, true);
        }
        $tool = new Tool();
        $save_apk = $save_path . "/cache.apk";
        if ($oss->ossDownload($path, $save_apk)) {
            /**大于300M***/
            if(floor(filesize($save_apk)/1024/1024)>300){
                $tool->clearFile($save_path);
                return $this->writeJson(200, ["code" => 0]);
            }else{
                if($oss->ossUpload($save_apk, $oss_path)){
                    $tool->clearFile($save_path);
                    /**
                     * @todo 谷歌同步
                     */
                    $post_google = [
                        "oss_path"=>$oss_path,
                        "sign"=>strtoupper(md5($oss_path."kiopmwhyusn"))
                    ];
                    $result = $tool->http_client("http://35.227.214.161/index/oss_to_google",$post_google);
//                    $result = $tool->http_client("http://34.117.236.200/index/oss_to_google",$post_google);
                    /***同步**/
                    $post_google["type"]=10;
                    $api_url = Config::getInstance()->getConf('API_URL');
                    $key_url = $api_url."api/async_apk";
                    $get_result = $tool->http_client($key_url,$post_google);
                    return $this->writeJson(200, ["code" => 1]);
                }
            }
        }
        $tool->clearFile($save_path);
        return $this->writeJson(200, ["code" => 0]);
    }


    protected function getTable($table, $user_id, $sn = 10)
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), $sn));
        return $table . "_" . $ext;
    }


    /**
     * 特定APP重签
     * @return bool
     */
    public function sign_resign_app()
    {
        $param = $this->request()->getRequestParam();
//        $app_id = $this->request()->getRequestParam('app_id');
//        $udid = $this->request()->getRequestParam('udid');
//        $append_data = $this->request()->getRequestParam('append_data');
        if (empty($param["app_id"]) || empty($param["udid"])||empty($param["tag"])||empty($param["cert_path"])) {
            return $this->writeJson(400, 'fail');
        }
//        TaskManager::getInstance()->async(new ReSign(["app_id" => $app_id, "udid" => $udid,"append_data"=>$append_data]));
        TaskManager::getInstance()->async(new ReSign($param));
        return $this->writeJson(200, 'success');
    }

    /**
     * 初始注入
     */
    public function alib_int_app(){
        $param = $this->request()->getRequestParam();
        if (empty($param["path"]) || empty($param["oss_path"]) || empty($param["app_id"])|| empty($param["account_id"])) {
            $this->writeJson(500, '缺少参数');
        } else {
            TaskManager::getInstance()->async(new InitApp($param));
            $this->writeJson(200, 'success');
        }
    }

    public function proxy_tb_oss(){
        $param = $this->request()->getRequestParam();
        if(empty($param["oss_path"])||empty($param["sign"])||$param["sign"]!=strtoupper(md5($param["oss_path"]."kiopmwhyusn"))){
            $this->writeJson(500, 'success');
        }else{
            TaskManager::getInstance()->async(new UploadZDOss($param));
            $this->writeJson(200, 'success');
        }
    }

    public function sign_v1_app(){
        $param = $this->request()->getRequestParam();
        if (empty($param["app_path"]) || empty($param["provisioning"]) || empty($param["cert_path"])|| empty($param["account_id"])) {
            $this->writeJson(500, '缺少参数');
        } else {
            TaskManager::getInstance()->async(new SignV1App($param));
            $this->writeJson(200, 'success');
        }
    }




}