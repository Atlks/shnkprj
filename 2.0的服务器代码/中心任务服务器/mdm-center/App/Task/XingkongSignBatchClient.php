<?php

namespace App\Task;

use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class XingkongSignBatchClient implements TaskInterface
{
    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
       var_dump($throwable->getMessage());
    }

    public function run(int $taskId, int $workerIndex) 
    {
        $data=RedisPool::invoke(function (Redis $redis) {
            $redis->select(8);
            $taskData=$redis->lPop('xingkong_sign_list_task');
            $leaveNum=$redis->lLen('xingkong_sign_list_task');
            return ['task_data'=>$taskData,'leave_num'=>$leaveNum];
        });
        $taskData=$data['task_data'];
        $leaveNum=$data['leave_num'];
        $data=null;
        if(!$taskData){
            $taskData=null;
            $leaveNum=null;
            return false;
        }
        $taskData=json_decode($taskData,true);
        //星空签名开始
        Logger::getInstance()->info('签名开启：'.$taskData['app_id']);
        if (empty($taskData['xk_appid'])) {
            
            $async = $this->http_client("http://34.150.24.210:85/index/sign_Xk_app", $taskData);
            Logger::getInstance()->info('新签名'.json_encode($async));
        } else {   
            $async = $this->http_client("http://34.150.24.210:85/index/sign_updata_Xk_app", $taskData);
            Logger::getInstance()->info('更新签名'.json_encode($async));
        }
        $statusCode = $async->getStatusCode();
        $async=null;
        if ($statusCode != 200) {
            Logger::getInstance()->error('签名失败，请稍后重试，app应用id：'.$taskData['app_id']);
            $taskData=null;
            $leaveNum=null;
            return false;
        }
        $statusCode=null;
        //签名开始
        RedisPool::invoke(function (Redis $redis) use (&$taskData) {
            $redis->select(8);
            $redis->set('sign_app_loading:'.$taskData['app_id'],$taskData['tag'],120);
        });
        //全部签名请求负载签名完后
        if($leaveNum<1){
            $async = $this->http_client("http://35.241.123.37:85/api/xingkong_sign_batch_renew", $taskData['sign_task_data']);
            $statusCode = $async->getStatusCode();
            $async=null;
            if ($statusCode != 200) {
                Logger::getInstance()->error('补充提交批量星空签名出错，'.json_encode($taskData['sign_task_data'],true));
                $taskData=null;
                $leaveNum=null;
                return false;
            }
            $statusCode=null;
        }
        $leaveNum=null;
        $taskData=null;
        return true;
    }

    function http_client($url, $data = [], $header = [])
    {
        $client = new HttpClient();
        $client->setUrl($url);
        $client->setTimeout(-1);
        if (!empty($header)) {
            $client->setHeaders($header);
        }
        if (!empty($data)) {
            $result = $client->post($data);
        } else {
            $result = $client->get();
        }
        return $result;
    }

}