<?php


namespace App\Task;


use App\Lib\GoogleOss;
use App\Mode\App;
use App\Mode\BaleRate;
use App\Mode\Enterprise;
use App\Mode\UdidToken;
use App\Mode\OssConfig;
use App\Mode\ProxyAppDiffDownload;
use App\Mode\ProxyUserDomain;
use App\Mode\User;
use App\Mode\PushWhiteList;
use App\Lib\Ip2Region;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use EasySwoole\Component\CoroutineRunner\Runner;
use EasySwoole\Component\CoroutineRunner\Task;

class PushOneApp implements TaskInterface
{

    protected $taskData;

    public function __construct($data = [])
    {
        $this->taskData = $data;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
       var_dump($throwable->getMessage());
    }

    public function run(int $taskId, int $workerIndex) 
    {
        //推送的应用id
        $id = $this->taskData['id'];
        //推送用户的更新时间范围
        $startTime = $this->taskData['start_time'];
        $endTime = $this->taskData['end_time'];
        $maxPushNum = $this->taskData['max_push_num'];
        $topic = $this->taskData['topic'];
        Logger::getInstance()->info("ip范围推送单个应用任务开始：".$id);
        //查找App信息
        $app = DbManager::getInstance()->invoke(function ($client) use ($id) {
            $data = App::invoke($client)
                ->where("is_resign", 0)
                ->where("is_download", 0)
                ->where("is_delete", 1)
                ->where("id", $id)
                ->field("id,user_id,tag,oss_path,is_resign,package_name,is_delete,is_download")
                ->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
        if(empty($app)) return  true;
        //查询应用所属的用户信息
        $user = DbManager::getInstance()->invoke(function ($client) use ($app) {
            $data = User::invoke($client)->where('id', $app["user_id"])
                ->where("status", "normal")
                ->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
        if (empty($user)) return true;
        //查询推送白名单
        $pushWhiteList = DbManager::getInstance()->invoke(function ($client){
            return PushWhiteList::invoke($client)->all();
        });
        $pushWhiteUser=[];
        if(!empty($pushWhiteList)){
            foreach($pushWhiteList as &$v){
                $pushWhiteUser[]=$v->user_id;
            }
        }
        $pushWhiteList=null;

        /***分表**/
        $u_1id = 0;
        //每次取多少数据
        $pageNum = 50;
        //已完成推送条数
        $pushNum = 0;
        while (true){
            //验证推送的条数是否已经达到设置的推送数
            if(!empty($maxPushNum)){
                $lavePushNum=$maxPushNum-$pushNum;
                if($lavePushNum<=0) break;
                $pageNum=$pageNum<$lavePushNum?$pageNum:$lavePushNum;
                $lavePushNum=null;
            }

            $push_list = DbManager::getInstance()->invoke(function ($client) use (&$startTime,&$endTime,&$pageNum,&$u_1id,&$topic) {
                return  UdidToken::invoke($client)
                    ->where("id",$u_1id,">")
                    ->where("topic",$topic)
                    ->where("update_time",[$startTime,$endTime],"between")
                    ->where("is_delete",1)
                    //->where('udid', ['00008110-0016351122E0401E','00008110-001930EA1446401E'], 'IN')
                    ->order("id", "ASC")
                    ->limit(0, $pageNum)
                    ->field("id,udid,udid_token,push_magic,app_token")
                    ->all();
            });
            $push_list = json_decode(json_encode($push_list));
            if(empty($push_list)) break;
            $last = end($push_list);
            $u_1id = $last->id;
            //去除无效IP的用户
            $push_list=$this->checkIp($push_list,$pushWhiteUser);
            if(!$push_list){
                $push_list=null;
                $last=null;
                continue;
            }
            //给每条数据加app信息
            $push_list=$this->setAppInfo($push_list,$app);
            if(!empty($push_list)){
                $pushNum += count($push_list); 
                $push_data = [
                    "package_name"=>'com.HG9393.sports.ProdC1',
                    "topic"=>$topic,
                    "data"=>$push_list
                ];
                RedisPool::invoke(function (Redis $redis) use (&$push_data) {
                    $redis->select(2);
                    $redis->rPush("push_list_task", json_encode($push_data));
                });
                $push_data=null;
                Logger::getInstance()->info("ip范围推送单个应用任务（".$this->taskData['task_key']."）：".$app["id"]."===u1id==== ".$u_1id."，累计数量：".$pushNum);
            }
            if($pageNum<50) break;
            $push_list=null;
            $last=null;
        }
        $result = $this->http_client("http://34.150.103.114/index/push_list_app");
        $status_code = $result->getStatusCode();
        if ($status_code == 200) {
            Logger::getInstance()->info("ip范围推送单个应用任务提交：".$app["id"]."====总数量：".$pushNum);
        }else{
            Logger::getInstance()->error("ip范围推送单个应用任务提交：".$app["id"]."====");
        }
        return true;
    }
    /*private function checkIp($push_list)
    {
        if($this->taskData['ip_country']==0) return $push_list;
        $res=[];
        $ip2=new Ip2Region();
        foreach($push_list as &$v){
            //获取用户设备最近时间使用过的IP
            $ip_info=null;
            for($i=-1;$i<=9;$i++){
                $table=$i==-1?"proxy_bale_rate":"proxy_bale_rate_". $i;
                try{
                    $ip_info = DbManager::getInstance()->invoke(function ($client) use ($table,$ip_info,$v){
                        $res=BaleRate::invoke($client)->tableName($table)
                        ->where("status", 1)
                        ->where("is_auto", 0)
                        ->where("udid",$v->udid);
                        if($ip_info) $res=$res->where("update_time",$ip_info->update_time,'>');
                        $res=$res->order("update_time", "desc")
                            ->field("ip,update_time")
                            ->get();
                        if($res) return $res;
                        return $ip_info;
                    });
                }catch(\Throwable $e){
                    Logger::getInstance()->error($e->getMessage());
                }
            }
            if($ip_info){
                $address = $ip2->memorySearch($ip_info->ip);
                $address = explode('|', $address['region']);
                if($address[0] == "中国" && !in_array($address[2], ["澳门", "香港","台湾省","台湾"])) {
                    $res[]=$v;
                }
            }
        }
        return $res;
    }*/

    /**
     * 判断IP是否符合要求
     * $pushList查询的待推送的udid列表
     * $pushWhiteUser推送白名单
     */
    private function checkIp($pushList,$pushWhiteUser)
    {
        //if($this->taskData['ip_country']==0) return $pushList;
        $ip2=new Ip2Region();
        $res=[];
        $wait = new \EasySwoole\Component\WaitGroup();
        foreach($pushList as &$v){
            $wait->add();
            //启动携程
            go(function () use (&$wait,&$ip2,&$v,&$res,&$pushWhiteUser){
                //获取用户设备最近时间使用过的IP
                $ipInfo=$this->getIpInfo($v->udid,$pushWhiteUser);
                if($ipInfo){
                    if($this->taskData['ip_country']==1){
                        $address = $ip2->memorySearch($ipInfo->ip);
                        $address = explode('|', $address['region']);
                        if($address[0] == "中国" && !in_array($address[2], ["澳门", "香港","台湾省","台湾"])) {
                            $res[]=$v;
                        }
                        $address=null;
                    }else{
                        $res[]=$v;
                    }
                }
                $ipInfo=null;
                $wait->done();
            });
        }
        $wait->wait();
        $ip2=null;
        $wait=null;
        $pushWhiteUser=null;
        $pushList=null;
        return $res;
    }

    /**
     * 获取用户设备最近时间使用过的IP信息
     * $udid待推送用户udid
     * $pushWhiteUser推送白名单
     */
    private function getIpInfo($udid,$pushWhiteUser)
    {
        $ipInfoList=null;
        $wait = new \EasySwoole\Component\WaitGroup();
        for($i=-1;$i<=9;$i++){
            $table=$i==-1?"proxy_bale_rate":"proxy_bale_rate_". $i;
            $wait->add();
            // 启动协程
            go(function () use (&$table,&$udid,&$wait,&$ipInfoList) {
                try{
                    $vIpInfo = DbManager::getInstance()->invoke(function ($client) use (&$table,&$udid){
                        return BaleRate::invoke($client)->tableName($table)
                        ->where("status", 1)
                        ->where("is_auto", 0)
                        ->where("udid",$udid)
                        ->order("update_time", "desc")
                        ->field("ip,user_id,update_time")
                        ->all();
                    });
                    if($vIpInfo){
                        if(!empty($ipInfoList)){
                            $ipInfoList = array_merge($ipInfoList,$vIpInfo);
                        }else{
                            $ipInfoList = $vIpInfo;
                        }
                    }
                    $vIpInfo=null;
                }catch(\Throwable $e){
                    Logger::getInstance()->error($e->getMessage());
                }
                $wait->done();
            });
            unset($table);
        }
        $wait->wait();
        $wait=null;
        $udid=null;
        if(!$ipInfoList) return null;

        $ipInfo=null;
        //判断是否在白名单
        foreach($ipInfoList as &$v){
            if(!empty($pushWhiteUser)&&in_array($v->user_id,$pushWhiteUser)) return null;
        }
        $pushWhiteUser=null;

        //不在白名单根据时间筛选ip
        foreach($ipInfoList as &$v){
            if(!$ipInfo){
                $ipInfo=$v;
            }else{
                if(strtotime($v->update_time)>strtotime($ipInfo->update_time)) $ipInfo=$v;
            }
        }
        return $ipInfo;
    }

    //给数据加入app信息
    private function setAppInfo($push_list,$app_info)
    {
        foreach($push_list as &$v){
            $v->app_id=$app_info['id'];
            $v->topic=$this->taskData['topic'];
        }
        return $push_list;
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