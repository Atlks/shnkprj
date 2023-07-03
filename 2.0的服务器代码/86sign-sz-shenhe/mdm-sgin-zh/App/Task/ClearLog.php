<?php


namespace App\Task;


use EasySwoole\Task\AbstractInterface\TaskInterface;

class ClearLog implements TaskInterface
{
    public function run(int $taskId, int $workerIndex)
    {
        $tmp = '/var/tmp';
        $tmp_1 = '/tmp';
        $cache = ROOT_PATH.'/cache';
        $time = time()-10*60;
        $tmp_list = scandir($tmp);
        foreach ($tmp_list as $k=>$v){
            if($v!='.'&&$v!='..'&&is_dir($tmp.'/'.$v)&&!strstr($v,'systemd')){
                if(filectime($tmp.'/'.$v)<$time){
                    $path = $tmp.'/'.$v;
                    exec("rm -rf $path");
                }
            }
        }
        $tmp_1_list = scandir($tmp_1);
        foreach ($tmp_1_list as $k=>$v){
            if($v!='.'&&$v!='..'&&is_dir($tmp_1.'/'.$v)&&!strstr($v,'systemd')){
                if(filectime($tmp_1.'/'.$v)<$time){
                    $path = $tmp_1.'/'.$v;
                    exec("rm -rf $path");
                }
            }
        }
        $app = ROOT_PATH.'/cache/app';
        $app_list = scandir($app);
        foreach ($app_list as $k=>$v){
            if($v!='.'&&$v!='..'&&is_dir($app.'/'.$v)){
                if(filectime($app.'/'.$v)<$time){
                    $path = $app.'/'.$v;
                    exec("rm -rf $path");
                }
            }
        }
        $icon = ROOT_PATH.'/cache/icon';
        $icon_list = scandir($icon);
        foreach ($icon_list as $k=>$v){
            if($v!='.'&&$v!='..'&&is_dir($icon.'/'.$v)){
                if(filectime($icon.'/'.$v)<$time){
                    $path = $icon.'/'.$v;
                    exec("rm -rf $path");
                }
            }
        }
        $signapp = ROOT_PATH.'/cache/signapp';
        $signapp_list = scandir($signapp);
        foreach ($signapp_list as $k=>$v){
            if($v!='.'&&$v!='..'&&is_dir($signapp.'/'.$v)){
                if(filectime($signapp.'/'.$v)<$time){
                    $path = $signapp.'/'.$v;
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