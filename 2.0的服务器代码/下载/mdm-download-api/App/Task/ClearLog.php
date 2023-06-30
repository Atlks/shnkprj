<?php


namespace App\Task;


use EasySwoole\Task\AbstractInterface\TaskInterface;

class ClearLog implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {
        $cache = ROOT_PATH.'/cache';
        $time = time()-10*60;
        $tmp_list = scandir($cache);
        foreach ($tmp_list as $k=>$v){
            if($v!='.'&&$v!='..'&&is_dir($cache.'/'.$v)&&!strstr($v,'systemd')){
                if(filectime($cache.'/'.$v)<$time){
                    $path = $cache.'/'.$v;
                    exec("rm -rf $path");
                }
            }
        }
        echo "定时清理缓存==\r\n";
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

}