<?php


namespace App\Task;


use App\Lib\Oss;
use App\Lib\Tool;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use Throwable;

class UploadZDOss implements TaskInterface
{

    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }

    public function run(int $taskId, int $workerIndex)
    {
        $data = $this->taskData;
        $oss_config = json_decode($data["async_oss_config"], true);
        $path = ROOT_PATH . "/cache/uploadapp/" . uniqid() . rand(111, 999) . '/';
        $info = pathinfo($data["oss_path"]);
        $oss = new Oss();
        $g_oss = new Oss($oss_config);
        $tool = new Tool();
        $oss_path = $data["oss_path"];
        $cache_name = "cache." . $info["extension"];
        try {
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
            if (!$oss->ossDownload($oss_path, $path . $cache_name)) {
                Logger::getInstance()->error("分流同步包下载失败   ".$oss_path);
                return false;
            }
            if (!is_file($path . $cache_name)) {
                Logger::getInstance()->error("分流同步包：包不存在" . $oss_path);
                $tool->clearFile($path);
                return false;
            }
            if ($g_oss->ossUpload($path . $cache_name, $oss_path)) {
                Logger::getInstance()->info("分流同步包：同步成功" . $oss_path);
            } else {
                Logger::getInstance()->error("分流同步包：包上传失败" . $oss_path);
            }
        }catch (Throwable $exception){
            Logger::getInstance()->error("分流同步包：包同步失败" . $oss_path);
        }
        $tool->clearFile($path);
        return true;
    }

    public function onException(Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

}