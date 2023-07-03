<?php


namespace App\Task;


use App\Lib\Oss;
use App\Lib\PropertyList;
use App\Lib\Tool;
use App\Mode\App;
use App\Mode\OssConfig;
use App\Mode\ProxyUserDomain;
use App\Mode\User;
use CFPropertyList\CFPropertyList;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use PhpZip\ZipFile;

/**注入签名，无mac***/
class AlibSign implements TaskInterface
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
        $password = $this->taskData["password"];
        $dllib = ROOT_PATH."/other/check_dlib/AliyunFangYuDunSDK.framework";
        $path = ROOT_PATH."/cache/signapp/". uniqid() . '/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $data = $this->taskData;
        $oss = new Oss();
        $tool = new Tool();
        if(!$oss->ossDownload($this->taskData["path"],$path."cache.ipa")){
            Logger::getInstance()->error("预签包下载失败");
            return false;
        }
        if(!$oss->ossDownload($this->taskData["cert_path"],$path."ios.p12")){
            Logger::getInstance()->error("预签证书下载失败");
            return false;
        }
        if(!$oss->ossDownload($this->taskData["provisioning_path"],$path."ios.mobileprovision")){
            Logger::getInstance()->error("预签描述文件下载失败");
            return false;
        }

        $app = DbManager::getInstance()->invoke(function ($client)use($data){
            $data = App::invoke($client)->where("id",$data["app_id"])->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
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
                        break;
                    }
                }
            }
        }
        exec("cd $path && $ausign --alib cache.ipa -i $dllib");
        if(!is_file($path."cache.ipa")){
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
        if(empty($app_folder)){
            Logger::getInstance()->error("预签失败,未找到Plist文件");
            return false;
        }
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
                $ipaInfo["AliyunTag"] = $app["tag"];
                $obj = $tool->array_no_empty($ipaInfo);
                $plist = new PropertyList($obj);
                $xml = $plist->xml();
                file_put_contents($fp, $xml);
            }
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

        if(empty($password)){
           $shell =  "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p \"\" -o sign.ipa -z 1 dailb.ipa";
        }else{
            $shell = "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p $password -o sign.ipa -z 1 dailb.ipa";
        }
        $log = $status = null;
        exec($shell,$log,$status);
        if(is_file($path."sign.ipa")){
           if($oss->ossUpload($path."sign.ipa",$this->taskData["oss_path"])){
                DbManager::getInstance()->invoke(function ($client)use($data){
                    App::invoke($client)->where("id",$data["app_id"])
                        ->update([
                            "account_id"=>$data["account_id"],
                            "oss_path"=>$data["oss_path"],
                            "update_time"=>date("Y-m-d H:i:s"),
                            'package_name' => $data['package_name'],
                        ]);
                });
               /**OSS分流***/
               $async_oss_config =  DbManager::getInstance()->invoke(function ($client)use ($data){
                   $app = App::invoke($client)->where("id", $data["app_id"])
                       ->where("is_delete", 1)
                       ->get();
                   if($app){
                       $user = User::invoke($client)->where('id', $app["user_id"])
                           ->where("status", "normal")
                           ->get();
                       if($user){
                           $proxy = ProxyUserDomain::invoke($client)->where('user_id', $user["pid"])->get();
                           if($proxy["oss_id"]){
                               $async_oss_config = OssConfig::invoke($client)->where("id",$proxy["oss_id"])->get();
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
                   "oss_path"=>$data["oss_path"],
                   "sign"=>strtoupper(md5($data["oss_path"]."kiopmwhyusn")),
                   "oss_id"=>$oss_id,
                   "async_oss_config"=>$async_oss_config,
               ];
               $result = $tool->http_client("http://35.227.214.161/index/g_oss_to_google",$post_google);
               Logger::getInstance()->info("预签成功===".$data["app_id"]."======google同步状态码： ".$result->getStatusCode()."===");
           }else{
               Logger::getInstance()->error("预签上传失败");
           }
        }else{
            var_dump($log);
            Logger::getInstance()->error("预签失败");
        }
        $tool->clearFile($path);
        return true;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        Logger::getInstance()->error("预签失败");
        Logger::getInstance()->error($throwable->getMessage());
    }

}