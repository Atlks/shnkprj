<?php


namespace App\Task;


use App\Lib\GoogleOss;
use App\Lib\Oss;
use App\Mode\App;
use App\Mode\BaleRate;
use App\Mode\Enterprise;
use App\Mode\OssConfig;
use App\Mode\ProxyUserDomain;
use App\Mode\User;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class XingkongSignatch implements TaskInterface
{

    //传递的任务执行参数
    protected $taskData;
    //签名账号信息
    protected $account;
    //配置的国内私有库信息
    protected $ossConfig;
    //国内私有云对象
    protected $oss;
    //配置的国外私有库
    protected $gOssConfig;
    //国外私有云对象
    protected $gOss;
    //谷歌云操作对象
    protected $googleOss;

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
        //查询账号信息及oss信息
        DbManager::getInstance()->invoke(function ($client){
            // $this->account = Enterprise::invoke($client)
            //     ->where('id', $this->taskData['account_id'])
            //     ->field('oss_path,tag,xk_appid')
            //     ->get();
            $this->ossConfig = OssConfig::invoke($client)->where('status', 1)->where('name','oss')->get();
            $this->gOssConfig = OssConfig::invoke($client)->where('status', 1)->where('name','g_oss')->get();
        });
        //实例化云操作对象
        $this->googleOss = new GoogleOss([
            'projectId' => '8QyD5vQLir0QKCjIxtK8yneibFQ7y7wl+z7WOSk5',
            'bucket' => 'qkmapp',
            'cdn' => 'https://storage.googleapis.com/qkmapp/',
            'keyFile' =>  '/extend/qkm-google.json'
        ]);
        $this->oss = new Oss($this->ossConfig);
        $this->gOss = new Oss($this->gOssConfig);
        /***分批次循环提交签名任务**/
        //最后执行到的应用id
        $appId = 0;
        //每次取多少数据
        $pageNum = 50;
        //已完成提交签名任务条数
        $taskNum = 0;
        Logger::getInstance()->info('提交批量星空签名任务开始：'.$this->taskData['task_key']);
       
        while (true){
            //查询符合条件的APP列表
            $appList = DbManager::getInstance()->invoke(function ($client) use (&$pageNum,&$appId) {
                $resList = App::invoke($client)
                    ->where('id',$appId,">")
                    ->where('status',$this->taskData['status'])
                    ->where('is_download',$this->taskData['is_download'])
                    ->where('is_delete',$this->taskData['is_delete']);
                    //->where('account_id',$this->taskData['account_id']);
                    //Logger::getInstance()->info('提交批量星空签名任务完成：'.json_encode($this->taskData));
                    // 根据用户使用量再做一次筛选
                    $flag = "";
                    
                    if(!empty($this->taskData['download_num']))
                    {
                        if($this->taskData['download_link']==1){
                            $flag = "install_num>" . $this->taskData['download_num'];
                        }elseif($this->taskData['download_link']==2){
                            $flag = "install_num=" . $this->taskData['download_num'];
                        }elseif($this->taskData['download_link']==3){
                            $flag = "install_num<" . $this->taskData['download_num'];
                        }
                        $resList = $resList->where($flag);
                        Logger::getInstance()->info('判断用户使用：'.$flag);
                    }   

                if(!empty($this->taskData['user_id'])) $resList = $resList->where('user_id',$this->taskData['user_id']);
                return $resList->order('id', 'ASC')
                    ->limit(0, $pageNum)
                    ->field('id,oss_path,user_id,update_time,package_name,is_resign,tag,xk_appid,type')
                    ->all();
                    //->where('id', ['139182','139181'], 'IN')
            });
            if(empty($appList)) break;
            $pageNum=count($appList);
            $last = end($appList);
            $appId = $last->id;
            //验证oss
            $appList=$this->checkOss($appList);
            if(!$appList){
                $appList=null;
                $last=null;
                continue;
            }
            //install_num


            /*
            //去除下载量不满足要求的APP
            if(!empty($this->taskData['start_time'])&&!empty($this->taskData['end_time'])&&!empty($this->taskData['download_num'])){
                $appList=$this->checkDownloadNum($appList);
                if(!$appList){
                    $appList=null;
                    $last=null;
                    continue;
                }
            }
            */
            //提交签名任务到负载服务器
            $taskNum+=$this->handSignTask($appList);
            if($pageNum<50) break;
            $appList=null;
            $last=null;
        }
        Logger::getInstance()->info('提交批量星空签名任务完成：'.$this->taskData['task_key'].'====总数量：'.$taskNum);
        return true;
    }

    /**
     * 检查app应用oss资源
     * $appList待检查oss资源的app应用列表
     * 返回符合条件的app应用列表
     */
    private function checkOss($appList)
    {
        $res = null;
        $wait = new \EasySwoole\Component\WaitGroup();
        foreach($appList as &$v){
            $wait->add();
            //启动携程
            go(function () use (&$wait,&$v,&$res){
                //判断谷歌云上是否存在文件
                $isGoogle = $this->googleOss->exists($v->oss_path);
                if(!$isGoogle){
                    $isGoogle=null;
                    Logger::getInstance()->info('(googleOss)国内OSS暂无资源，请先点击 同步阿里云，APPID：'.$v->id);
                }else{
                    //检查国外私有云是否存在文件
                    $isGOss = $this->gOss->is_exit($v->oss_path);
                    if (!$isGOss) {
                        $isGOss=null;
                        Logger::getInstance()->info('(国外私有云)国内OSS暂无资源，请先点击 同步阿里云，APPID：'.$v->id);
                    }
                    else
                        $res[]=$v;
                }
                $wait->done();
            });
        }
        $wait->wait();
        $appList=null;
        return $res;
    }


    private function checkInstallNum($appList)
    {
        $res=null;
        $wait = new \EasySwoole\Component\WaitGroup();
        foreach($appList as &$v){
            $wait->add();
            // 启动协程
            go(function () use (&$v,&$wait,&$res) {
                //获取用户的pid
                $user = DbManager::getInstance()->invoke(function ($client) use (&$v) {
                    return User::invoke($client)->where('id', $v->user_id)->where('status', 'normal')->field('pid')->get();
                });
                if (!empty($user)){
                    $v->pid=$user->pid;
                    $table = $this->getTable("proxy_bale_rate", $user->pid);
                    try{
                        $downNum = DbManager::getInstance()->invoke(function ($client) use (&$table,&$v){
                            return App::invoke($client)->tableName($table)
                            ->where('status', 1)
                            ->where('is_auto', 0)
                            ->where('app_id',$v->id)
                            ->count('id');
                        });
                        //根据条件判断该app应用否下载量符合要求
                        if($this->taskData['download_link']==1){
                            if($downNum>$this->taskData['download_num']) $res[]=$v;
                        }elseif($this->taskData['download_link']==2){
                            if($downNum==$this->taskData['download_num']) $res[]=$v;
                        }elseif($this->taskData['download_link']==3){
                            if($downNum<$this->taskData['download_num']) $res[]=$v;
                        }
                        $downNum=null;
                    }catch(\Throwable $e){
                        Logger::getInstance()->error($e->getMessage());
                    }
                }
                $wait->done();
            });
        }
        $wait->wait();
        $appList=null;
        return $res;
    }

    /**
     * 检查应用下载次数是否满足要求
     * $appList待检查下载次数的app应用列表
     * 返回符合条件的app应用列表
     */
    private function checkDownloadNum($appList)
    {
        $res=null;
        $wait = new \EasySwoole\Component\WaitGroup();
        foreach($appList as &$v){
            $wait->add();
            // 启动协程
            go(function () use (&$v,&$wait,&$res) {
                //获取用户的pid
                $user = DbManager::getInstance()->invoke(function ($client) use (&$v) {
                    return User::invoke($client)->where('id', $v->user_id)->where('status', 'normal')->field('pid')->get();
                });
                if (!empty($user)){
                    $v->pid=$user->pid;
                    $table = $this->getTable("proxy_bale_rate", $user->pid);
                    try{
                        $downNum = DbManager::getInstance()->invoke(function ($client) use (&$table,&$v){
                            return BaleRate::invoke($client)->tableName($table)
                            ->where('status', 1)
                            ->where('is_auto', 0)
                            ->where('app_id',$v->id)
                            ->count('id');
                        });
                        //根据条件判断该app应用否下载量符合要求
                        if($this->taskData['download_link']==1){
                            if($downNum>$this->taskData['download_num']) $res[]=$v;
                        }elseif($this->taskData['download_link']==2){
                            if($downNum==$this->taskData['download_num']) $res[]=$v;
                        }elseif($this->taskData['download_link']==3){
                            if($downNum<$this->taskData['download_num']) $res[]=$v;
                        }
                        $downNum=null;
                    }catch(\Throwable $e){
                        Logger::getInstance()->error($e->getMessage());
                    }
                }
                $wait->done();
            });
        }
        $wait->wait();
        $appList=null;
        return $res;
    }

    /**
     * 提交签名任务到推送服务器
     * $appList待提交签名任务的app应用列表
     * 返回提交的任务数
     */
    /*private function handSignTask($appList)
    {
        $num=0;
        foreach($appList as &$v){
            //检查是否正在签名
            $isSign=RedisPool::invoke(function (Redis $redis) use (&$v) {
                $redis->select(8);
                return $redis->get('sign_app_loading:'.$v->id);
            });
            if(empty($v->update_time)||!empty($isSign)){
                Logger::getInstance()->info('签名进行中，无法更换证书，app应用id'.$v->id);
                continue;
            }
            $isSign=null;
            if(!isset($v->pid)){
                $user = DbManager::getInstance()->invoke(function ($client) use (&$v) {
                    return User::invoke($client)->where('id', $v->user_id)->where('status', 'normal')->field('pid')->get();
                });
                if(empty($user)) continue;
                $v->pid=$user->pid;
                $user = null;
            }
            //OSS分流
            $asyncOssConfig = null;
            $proxy = DbManager::getInstance()->invoke(function ($client) use (&$v) {
                return ProxyUserDomain::invoke($client)->where('user_id', $v->pid)->field('oss_id')->get();
            });
            if($proxy&&$proxy->oss_id) {
                $asyncOssConfig = DbManager::getInstance()->invoke(function ($client) use (&$proxy) {
                    return OssConfig::invoke($client)->where('id', $proxy->oss_id)->get();
                });
            }
            //组装提交签名任务数据
            $ansycData = [
                'path' => $v->oss_path,
                'oss_path' => 'app/' . date('Ymd') . '/' . $v->tag . '.ipa',
                'cert_path' => $this->account->oss_path,
                'provisioning_path' => $this->account->oss_provisioning,
                'password' => $this->account->password,
                'account_id' => $this->account->id,
                'app_id' => $v->id,
                'package_name' => $v->package_name,
                'is_resign' => $v->is_resign,
                'tag' => $v->tag,
                'oss_id' => $proxy->oss_id??0,
                'async_oss_config' => $asyncOssConfig
            ];
            $proxy=null;
            $asyncOssConfig=null;
            //国内负载签名
            $async = $this->http_client("http://39.108.128.140:85/index/alib_int_app", $ansycData);
            $ansycData=null;
            $statusCode = $async->getStatusCode();
            $async=null;
            if ($statusCode != 200) {
                Logger::getInstance()->error('签名失败，请稍后重试，app应用id'.$v->id);
                continue;
            }
            $statusCode=null;
            //签名开始
            RedisPool::invoke(function (Redis $redis) use (&$v) {
                $redis->select(8);
                $redis->get('sign_app_loading:'.$v->id,$v->tag,120);
            });
            $num+=1;      
        }
        $appList=null;
        $v=null;
        Logger::getInstance()->info('提交linux签名任务：'.$this->taskData['task_key'].'，数量：'.$num);
        return $num;
    }*/

    //定时任务版本
    private function handSignTask($appList)
    {
        Logger::getInstance()->info('签名进行中，RedisPool'.json_encode($appList));
        $num=0;
        foreach($appList as &$v){
            //检查是否正在签名
            $isSign=RedisPool::invoke(function (Redis $redis) use (&$v) {
                $redis->select(8);
                return $redis->get('sign_app_loading:'.$v->id);
            });
            if(!empty($isSign)){
                Logger::getInstance()->info('签名进行中，app应用id：'.$v->id);
                continue;
            }
            Logger::getInstance()->info('签名进行中，RedisPool'.$v->id);
            $isSign=null;
            if(!isset($v->pid)){
                $user = DbManager::getInstance()->invoke(function ($client) use (&$v) {
                    return User::invoke($client)->where('id', $v->user_id)->where('status', 'normal')->field('pid')->get();
                });
                if(empty($user)) continue;
                $v->pid=$user->pid;
                $user = null;
            }
            //OSS分流
            $asyncOssConfig = null;
            $proxy = DbManager::getInstance()->invoke(function ($client) use (&$v) {
                return ProxyUserDomain::invoke($client)->where('user_id', $v->pid)->field('oss_id')->get();
            });
            if($proxy&&$proxy->oss_id) {
                $asyncOssConfig = DbManager::getInstance()->invoke(function ($client) use (&$proxy) {
                    return OssConfig::invoke($client)->where('id', $proxy->oss_id)->get();
                });
            }
            //组装提交签名任务数据
            $ansycData = [
                'user_id' => $v->user_id,
                'app_path' => $v->oss_path,
                'app_id' => $v->id,
                'xk_appid' => $v->xk_appid,
                'is_resign' => $v->is_resign,
                'tag' => $v->tag,
                'oss_id' => $proxy->oss_id??0,
                'async_oss_config' => $asyncOssConfig,
                'app_type' => $v->type,
                'sign_task_data' => $this->taskData
            ];
            $proxy=null;
            $asyncOssConfig=null;
            //异步推送签名任务
            try{
                RedisPool::invoke(function (Redis $redis) use (&$ansycData) {
                    $redis->select(8);
                    $redis->rPush('xingkong_sign_list_task', json_encode($ansycData));
                    Logger::getInstance()->info('签名进行中，RedisPool'.json_encode($ansycData));
                });
            }catch(\Throwable $e){
                Logger::getInstance()->error('星空签名加入待提交列表:'.$e->getMessage());
            }
            $ansycData=null;
            Logger::getInstance()->info('签名进行中，num'.$num);
            $num+=1;      
        }
        $appList=null;
        $v=null;
        if($num>0) Logger::getInstance()->info('提交星空签名任务：'.$this->taskData['task_key'].'，数量：'.$num);
        return $num;
    }

    //获取对应的APP下载付费数据存放的分表名
    private function getTable($table = "", $user_id = "")
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), 10));
        return $table . "_" . $ext;
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