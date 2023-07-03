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
use Throwable;

class TestAlibApp implements TaskInterface
{
    public function onException(Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    public function run(int $taskId, int $workerIndex)
    {
        $app_list = DbManager::getInstance()->invoke(function ($client) {
            return App::invoke($client)->where("is_delete", 1)
                ->where("status", 1)
                ->where("is_resign", 1)
                ->where("is_download", 0)
                ->order("id", "ASC")
                ->field("id,oss_path,name,tag")
                ->all();
        });
        $app_list = json_decode(json_encode($app_list), true);
        $ausign = ROOT_PATH . '/sign/kxsign';
        $dllib_resign = ROOT_PATH . "/other/AliyunFangYuDunSDK.framework";
        $tool = new Tool();
        $error_number=0;
        $success_number=0;
        foreach ($app_list as $v) {
            $path = ROOT_PATH . "/cache/alib/" . uniqid() . '/';
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
            $oss = new Oss();
            try {
                if (!$oss->ossDownload($v["oss_path"], $path . "cache.ipa")) {
                    Logger::getInstance()->error("测试===包下载失败==".$v["id"]);
                    return false;
                }
                if (!is_file($path . "cache.ipa")) {
                    Logger::getInstance()->error("测试===包下载失败==".$v["id"]);
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
                        foreach ($llib as $val) {
                            if ($val['name'] == '@rpath/AliyunFYDunSDK.framework/AliyunFYDunSDK') {
                                exec("cd $path && $ausign --dlib cache.ipa -i '@rpath/AliyunFYDunSDK.framework/AliyunFYDunSDK##" . $val['file'] . "'");
                            }
                            if ($val['name'] == '@rpath/AliyunFangYuDunSDK.framework/AliyunFangYuDunSDK') {
                                exec("cd $path && $ausign --dlib cache.ipa -i '@rpath/AliyunFangYuDunSDK.framework/AliyunFangYuDunSDK##" . $val['file'] . "'");
                            }
                        }
                    }
                }
                /***注入***/
                $log = $status = null;
                exec("cd $path && $ausign --alib cache.ipa -i $dllib_resign  -o dailb.ipa");
                if (!is_file($path . "/dailb.ipa")) {
                    Logger::getInstance()->error("测试===注入包==".$v["id"]);
                    return false;
                }
                exec("cd $path && unzip -O gb2312 dailb.ipa");
                $tmp_dir_list = scandir($path . 'Payload');
                $fp = null;
                foreach ($tmp_dir_list as $vdir) {
                    if ($vdir != '.' && $vdir != '..' && is_dir($path . 'Payload/' . $vdir) && strstr($vdir, '.app')) {
                        // 拼接plist文件完整路径
                        $fp = $path . '/Payload/' . $vdir . '/Info.plist';
                        if (is_file($fp)) {
                            break;
                        }
                    }
                }
                if (empty($fp)) {
                    Logger::getInstance()->error("测试===未找到Plist文件==".$v["id"]);
                    return false;
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
                $ipaInfo["AliyunTag"] = $v["tag"];
                $obj = $tool->array_no_empty($ipaInfo);
                $plist = new PropertyList($obj);
                $xml = $plist->xml();
                file_put_contents($fp, $xml);
                $log = $status = null;
                exec("cd $path && zip -q -r -1 cache_alib.ipa  Payload", $log, $status);

                if (is_file($path . "cache_alib.ipa")) {
                    $oss_path = "app/" . date("Ymd") . "/" . $v["tag"] . ".ipa";
                    if ($oss->ossUpload($path . "cache_alib.ipa", $oss_path)) {
                        $update = [
                            "oss_path" => $oss_path,
                            "update_time" => date("Y-m-d H:i:s"),
                        ];
                        DbManager::getInstance()->invoke(function ($client) use ($v, $update) {
                            App::invoke($client)->where("id", $v["id"])->update($update);
                        });
                        Logger::getInstance()->info("测试===初始注入成功=====".$v["id"]);
                        $success_number++;
                    } else {
                        Logger::getInstance()->error("测试===上传失败==".$v["id"]);
                    }
                } else {
                    Logger::getInstance()->error("测试===压缩失败==".$v["id"]);
                    var_dump($log);
                }
            } catch (Throwable $exception) {
                var_dump($exception->getMessage());
                $error_number++;
            }
            $tool->clearFile($path);
        }
        Logger::getInstance()->info("测试===重注入完成===成功：$success_number====失败：$error_number===");
        return true;
    }

}