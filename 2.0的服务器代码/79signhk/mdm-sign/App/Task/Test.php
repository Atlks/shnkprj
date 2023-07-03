<?php


namespace App\Task;


use App\Lib\Oss;
use App\Lib\PropertyList;
use App\Lib\Tool;
use App\Mode\App;
use CFPropertyList\CFPropertyList;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use PhpZip\ZipFile;

class Test implements TaskInterface
{

    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData= $taskData;
    }

    public function run(int $taskId, int $workerIndex)
    {
        Logger::getInstance()->info("测试注入");
        $sign = "/opt/zsign/zsign";
        $ausign = ROOT_PATH . '/sign/kxsign';
        $password = $this->taskData["password"];
        $dllib = ROOT_PATH."/other/test/AliyunFYDunSDK.framework";
        $path = ROOT_PATH."/cache/test/". uniqid() . '/';
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
        exec("cd $path && $ausign --alib cache.ipa -i $dllib");
        if(!is_file($path."cache.ipa")){
            Logger::getInstance()->error("预签注入失败");
            return false;
        }
        $zipFile = new ZipFile();
        $zipFile->openFile($path."cache.ipa");
        $matche = $zipFile->matcher();
        $matche->match('/Payload\/([^\/]*)\/Info\.plist$/i');
        $list = $matche->getMatches();
        $zipFile->close();
        $zipFile = new ZipFile();
        $zipFile->openFile($path."cache.ipa");
        $zipFile->extractTo($path);
        $zipFile->close();
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
        exec("cd $path && zip -q -r  dailb.ipa  Payload");
        if(empty($password)){
            $shell =  "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p \"\" -o sign.ipa -z 1 dailb.ipa";
        }else{
            $shell = "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p $password -o sign.ipa -z 1 dailb.ipa";
        }
        exec($shell,$log,$status);
        if(is_file($path."sign.ipa")){
            if($oss->ossUpload($path."sign.ipa",$this->taskData["oss_path"])){
                Logger::getInstance()->info("预签成功");
                DbManager::getInstance()->invoke(function ($client)use($data){
                    App::invoke($client)->where("id",$data["app_id"])
                        ->update([
                            "account_id"=>$data["account_id"],
                            "oss_path"=>$data["oss_path"],
                            "update_time"=>date("Y-m-d H:i:s"),
                            'package_name' => $data['package_name'],
                        ]);
                });
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
        // TODO: Implement onException() method.
        var_dump($throwable->getMessage());
    }

}