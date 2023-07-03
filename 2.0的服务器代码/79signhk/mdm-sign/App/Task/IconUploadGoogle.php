<?php


namespace App\Task;


use App\Lib\GoogleOss;
use App\Lib\Oss;
use App\Lib\Tool;
use App\Mode\App;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class IconUploadGoogle implements TaskInterface
{
    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    public function run(int $taskId, int $workerIndex)
    {
        $tool = new Tool();
        $id = 0;
        $num = 0;
        while (true) {
            $num++;
            $list = null;
            $list = DbManager::getInstance()->invoke(function ($client) use ($id) {
                $data = App::invoke($client)
                    ->where("id", $id, '>')
                    ->order("id", "ASC")
                    ->limit(0, 500)
                    ->field("id,name,icon,download_bg,imgs,old_icon")
                    ->all();
                if ($data) {
                    return json_decode(json_encode($data), true);
                } else {
                    return null;
                }
            });
            $public_oss_config = Config::getInstance()->getConf('PUBLIC_G_OSS');
            $oss = new Oss($public_oss_config);
            $google_oss = new GoogleOss();
            foreach ($list as $k=>$v){
                $id = $v["id"];
                if(empty($v["old_icon"])){
                    continue;
                }
                $path = ROOT_PATH . "/cache/imgs/" . $v["id"] . '/';
                if (!is_dir($path)) {
                    mkdir($path, 0777, true);
                }
                $icon = substr($v["old_icon"], strpos($v["old_icon"], 'upload/'));
                $cache_icon = pathinfo($icon)["basename"];
                if($google_oss->ossDownload($icon,$path.$cache_icon)){
                    if(!$oss->ossUpload($path.$cache_icon,$icon)){
                        $oss->ossUpload($path.$cache_icon,$icon);
                    }
                }
//                $icon = substr($v["icon"], strpos($v["icon"], 'upload/'));
//                $cache_icon = pathinfo($icon)["basename"];
//                if($google_oss->ossDownload($icon,$path.$cache_icon)){
//                    $oss->ossUpload($path.$cache_icon,$icon);
//                }
//                /**下载背景*/
//                if(!empty($v["download_bg"])){
//                    $download_bg = substr($v["download_bg"], strpos($v["download_bg"], 'upload/'));
//                    $cache_download_bg = pathinfo($download_bg)["basename"];
//                    if($google_oss->ossDownload($download_bg,$path.$cache_download_bg)){
//                        $oss->ossUpload($path.$cache_download_bg,$download_bg);
//                    }
//                }
//                /**截图**/
//                if(!empty($v["imgs"])){
//                    $cache_imgs = array_filter(explode(',', $v['imgs']));
//                    foreach ($cache_imgs as $key => $val) {
//                        $img = substr($val, strpos($val, 'upload/'));
//                        $cache_img = pathinfo($img)["basename"];
//                        if($google_oss->ossDownload($img,$path.$cache_img)){
//                            $oss->ossUpload($path.$cache_img,$img);
//                        }
//                    }
//                }
                $tool->clearFile($path);
                Logger::getInstance()->info("icon同步完成===".$id."==");
            }
            if (empty($list) || count($list) < 300) {
                break;
            }
        }
    }

}