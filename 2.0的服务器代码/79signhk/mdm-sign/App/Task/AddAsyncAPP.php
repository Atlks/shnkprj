<?php


namespace App\Task;


use App\Lib\Oss;
use App\Lib\PropertyList;
use App\Lib\Tool;
use App\Mode\App;
use App\Mode\Enterprise;
use CFPropertyList\CFPropertyList;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use Throwable;

/**1.0同步到2.0*/
class AddAsyncAPP implements TaskInterface
{

    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }

    public function onException(Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    public function run(int $taskId, int $workerIndex)
    {
        $sign = "/opt/zsign/zsign";
        $ausign = ROOT_PATH . '/sign/kxsign';
//        $dllib = ROOT_PATH . "/other/AliyunFYDunSDK.framework";
        $dllib = ROOT_PATH."/other/check_dlib/AliyunFangYuDunSDK.framework";
        $path = ROOT_PATH . "/cache/signapp/" . uniqid() . '/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $tag = uniqid();
        $tool = new Tool();
        $data = $this->taskData;
        try {
            $url = "http://8.210.182.34/api/index/get_app_info";
            $result = $this->http_client($url, [
                "sign" => "mdmsignchangappaddd",
                "app_id" => $data["app_id"],
                "type" => $data["type"]
            ]);
            $result_data = $result->getBody();
            $result_data = json_decode($result_data, true);
            if (empty($result_data) || $result_data["code"] != 200) {
                Logger::getInstance()->error("APP转移错误");
                return true;
            }
            $app = $result_data["data"]["app"];

            /**私有库**/
            $oss = new Oss();
            /**公共库**/
            $img_oss = new Oss(json_decode($data["oss_read_img"], true));
            /***原始库**/
            $yum_oss = new Oss($result_data["data"]["oss_config"]);
            $yum_img_oss = new Oss($result_data["data"]["oss_read_config"]);
            /**证书**/
            $account = DbManager::getInstance()->invoke(function ($client) {
                $data = Enterprise::invoke($client)->where('status', 1)->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
            $password = $account["password"];
            if (!$yum_oss->ossDownload($app["oss_path"], $path . "cache.ipa")) {
                Logger::getInstance()->error("转移APP包下载失败");
                return false;
            }
            if (!$oss->ossDownload($account["oss_path"], $path . "ios.p12")) {
                Logger::getInstance()->error("转移APP证书下载失败");
                return false;
            }
            if (!$oss->ossDownload($account["oss_provisioning"], $path . "ios.mobileprovision")) {
                Logger::getInstance()->error("转移APP描述文件下载失败");
                return false;
            }
            /**安卓下载**/
            if (!empty($app["apk_url"])) {
                if (!strstr($app["apk_url"], 'http')) {
                    if (!$yum_oss->ossDownload($app["apk_url"], $path . "cache.apk")) {
                        Logger::getInstance()->error("转移APP安卓下载失败");
                        return false;
                    }
                }
            }
            /**ICON**/
            $icon = "";
            $yum_img_oss->ossDownload($app["icon"], $path . "icon.png");
            $save_icon = "upload/" . date("Ymd") . "/" . $tag . ".png";
            if ($img_oss->ossUpload($path . "icon.png", $save_icon)) {
                $icon = $img_oss->oss_url() . $save_icon;
            }
            if (!empty($app["imgs"])) {
                $imgs = [];
                foreach ($app["imgs"] as $v) {
                    $info = pathinfo($v);
                    $cahe_img = $path . time() . rand(100, 999) . "." . $info['extension'];
                    if ($yum_img_oss->ossDownload($v, $cahe_img)) {
                        $save_img = "upload/" . date("Ymd") . "/" . md5_file($cahe_img) . "." . $info["extension"];
                        if ($img_oss->ossUpload($cahe_img, $save_img)) {
                            $imgs[] = $img_oss->oss_url() . $save_img;
                        }
                    }
                }
                $imgs = implode(',', $imgs);
            } else {
                $imgs = "";
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
                            break;
                        }
                    }
                }
            }

            exec("cd $path && $ausign --alib cache.ipa -i $dllib");
            if (!is_file($path . "cache.ipa")) {
                Logger::getInstance()->error("转移APP注入失败");
                return false;
            }
            exec("cd $path && unzip -O gb2312 cache.ipa");
            $tmp_dir_list = scandir($path . 'Payload');

            $app_name = "";
            $fp = "";
            foreach ($tmp_dir_list as $v) {
                if ($v != '.' && $v != '..' && is_dir($path . 'Payload/' . $v) && strstr($v, '.app')) {
                    $app_name = $v;
                    // 拼接plist文件完整路径
                    $fp = $path . '/Payload/' . $v . '/Info.plist';
                    break;
                }
            }
            if (empty($fp)) {
                Logger::getInstance()->error("转移APP失败,未找到Plist文件");
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
            $ipaInfo["AliyunTag"] = $tag;
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

            exec("cd $path && zip -q -r -1 dailb.ipa  Payload");

            if (empty($password)) {
                $shell = "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p \"\" -o sign.ipa -z 1 dailb.ipa";
            } else {
                $shell = "cd $path && $sign -k ios.p12 -m ios.mobileprovision -p $password -o sign.ipa -z 1 dailb.ipa";
            }
            $log = $status = null;
            exec($shell, $log, $status);
            if (is_file($path . "sign.ipa")) {
                $oss_path = "app/" . date("Ymd") . "/$tag.ipa";
                /**
                 * 公共库
                 */
                $public_oss_config = Config::getInstance()->getConf('PUBLIC_G_OSS');
                $public_oss = new Oss($public_oss_config);
                if ($oss->ossUpload($path . "sign.ipa", $oss_path) && $public_oss->ossUpload($path . "sign.ipa", $oss_path)) {

                    if (!empty($app["apk_url"])) {
                        if (!strstr($app["apk_url"], 'http')) {
                            $apk_url = 'apk/' . date("Ymd") . "/" . $tag . '.apk';
                            if (!$oss->ossUpload($path . "cache.apk", $apk_url)) {
                                $apk_url = "";
                            }
                        } else {
                            $apk_url = $app["apk_url"];
                        }
                    } else {
                        $apk_url = "";
                    }
                    $add_data = [
                        'name' => $app['name'],
                        'path' => "",
                        'tag' => $tag,
                        'user_id' => $data["user_id"],
                        'icon' => $icon,
                        'ipa_data_bak' => $app['ipa_data_bak'],
                        'package_name' => $app['package_name'],
                        'version_code' => $app['version_code'],
                        'bundle_name' => $app['bundle_name'],
                        'status' => 1,
                        'filesize' => $app['filesize'],
                        'desc' => $app['desc'],
                        'imgs' => $imgs,
                        'score_num' => $app['score_num'],
                        'introduction' => $app['introduction'],
                        'oss_path' => $oss_path,
                        'apk_url' => $apk_url,
                        'create_time' => date('Y-m-d H:i:s'),
                        'update_time' => date('Y-m-d H:i:s'),
                        'remark' => $app['remark'],
                        'is_vaptcha' => 1,
                        'short_url' => $data["short_url"],
                        'is_st' => 0,
                        'lang' => $app["lang"],
                        'comment' => $app["comment"] ?? "",
                        'comment_name' => $app["comment_name"] ?? "",
                        'mode' => 2,
                        'is_stop' => 0,
                    ];
                    DbManager::getInstance()->invoke(function ($client) use ($add_data) {
                        App::invoke($client)->data($add_data)->save();
                    });
                    /**
                     * @todo 谷歌同步
                     */
                    $post_google = [
                        "oss_path"=>$oss_path,
                        "sign"=>strtoupper(md5($oss_path."kiopmwhyusn"))
                    ];
                    $result = $tool->http_client("http://35.227.214.161/index/g_oss_to_google",$post_google);
                    Logger::getInstance()->info("转移APP成功");
                }else{
                    Logger::getInstance()->error("转移APP上传失败");
                }
            } else {
                var_dump($log);
                Logger::getInstance()->error("转移APP失败");
            }
            $tool->clearFile($path);
        } catch (Throwable $exception) {
            Logger::getInstance()->error("转移APP失败");
            $tool->clearFile($path);
        }
        return true;
    }


    function http_client($url, $data = [], $header = [])
    {
        $client = new HttpClient();
        $client->setUrl($url);
        $client->setTimeout(60);
        if (!empty($header)) {
            $client->setHeaders($header);
        }
        if (!empty($data)) {
//            $client->setContentTypeFormData();
            $result = $client->post($data);
        } else {
            $result = $client->get();
        }
        return $result;
    }

}