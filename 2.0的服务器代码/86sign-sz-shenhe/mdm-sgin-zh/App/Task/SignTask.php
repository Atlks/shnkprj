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

class SignTask implements TaskInterface
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
//        $dllib = ROOT_PATH."/other/AliyunFYDunSDK.framework";
        $dllib = ROOT_PATH."/other/check_dlib/AliyunFangYuDunSDK.framework";
        $path = ROOT_PATH."/cache/signapp/". uniqid() . '/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $data = $this->taskData;
        $oss = new Oss();
        $tool = new Tool();
        try {

            if (!$oss->ossDownload($this->taskData["path"], $path . "cache.ipa")) {
                Logger::getInstance()->error("预签包下载失败");
                return false;
            }
            if (!$oss->ossDownload($this->taskData["cert_path"], $path . "ios.p12")) {
                Logger::getInstance()->error("预签证书下载失败");
                return false;
            }
            if (!$oss->ossDownload($this->taskData["provisioning_path"], $path . "ios.mobileprovision")) {
                Logger::getInstance()->error("预签描述文件下载失败");
                return false;
            }

            $app = DbManager::getInstance()->invoke(function ($client) use ($data) {
                $data = App::invoke($client)->where("id", $data["app_id"])->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
            exec("cd $path && $ausign --alib cache.ipa -i $dllib");
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
                    $app_name = $v;
                    $list[] = 'Payload/' . $v . "/Info.plist";
                    break;
                }
            }
            if (empty($app_folder)) {
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

            if (empty($password)) {
                $shell = "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p \"\" -o sign.ipa -z 1 dailb.ipa";
            } else {
                $shell = "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p $password -o sign.ipa -z 1 dailb.ipa";
            }
            $log = $status =null;
            exec($shell, $log, $status);
            if (is_file($path . "sign.ipa")) {
                if ($oss->ossUpload($path . "sign.ipa", $this->taskData["oss_path"])) {
                    DbManager::getInstance()->invoke(function ($client) use ($data) {
                        App::invoke($client)->where("id", $data["app_id"])
                            ->update([
                                "account_id" => $data["account_id"],
                                "oss_path" => $data["oss_path"],
                                "update_time" => date("Y-m-d H:i:s"),
                                'package_name' => $data['package_name'],
                            ]);
                    });
//                    /**mac 签名***/
//                    $post = [
//                        'path' => $app['oss_path'],
//                        'package_name' => $app['package_name'],
//                        'oss_path' =>  'app/' . date('Ymd') . '/' . $app["tag"] . '.ipa',
//                        'cert_path' => $data["cert_path"],
//                        'provisioning_path' => $data["provisioning_path"],
//                        'password' => $data["password"],
//                        'account_id' => $data["account_id"],
//                        'app_id' => $app['id'],
//                        "is_overseas"=>10,
//                        "tag"=>$app["tag"],
//                    ];
//                    $post_result = $tool->http_client("http://207.254.52.185:85/index/sign",$post);
//                    $status_code = $post_result->getStatusCode();
                    Logger::getInstance()->info("预签成功=====");
                } else {
                    Logger::getInstance()->error("预签上传失败");
                }
            } else {
                Logger::getInstance()->error("预签失败");
            }
        }catch (\Throwable $exception ){
            Logger::getInstance()->error($exception->getMessage());
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