<?php


namespace App\HttpController;


use App\Lib\Tool;
use App\Mode\App;
use App\Mode\BaleRate;
use App\Mode\User;
use App\Task\ApkDownloadCheck;
use App\Task\AppAllPush;
use App\Task\AppAllSign;
use App\Task\AppAutoStart;
use App\Task\AppDiff;
use App\Task\AppPush;
use App\Task\AppWarningNotice;
use App\Task\AsyncOss;
use App\Task\CheckHostStatus;
use App\Task\CheckInstallIdfv;
use App\Task\ClearApp;
use App\Task\ClearInstallAppCall;
use App\Task\ClearInstallCallback;
use App\Task\ClearResignCallBack;
use App\Task\ClearUdidToken;
use App\Task\ClearUser;
use App\Task\ProxyAsyncOss;
use App\Task\PushOneApp;
use App\Task\RedisUdidList;
use App\Task\UrlChangeNotice;
use App\Task\WxUrlChangeNotice;
use App\Task\AlibSignBatch;
use App\Task\XingkongSignatch;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\HttpClient\Exception\InvalidUrl;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;
use EasySwoole\ORM\Exception\Exception;
use EasySwoole\Pool\Exception\PoolEmpty;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use Throwable;

class Api extends Controller
{
    /**
     * 回调
     * @param string $message
     * @param null $data
     * @param int $code
     * @return bool
     */
    protected function success($message = "", $data = null, $code = 200)
    {
        $result = [
            "code" => $code,
            "msg" => $message,
            "time" => (string)time(),
            "data" => $data
        ];
        if (!$this->response()->isEndResponse()) {
            $this->response()->write(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->response()->withHeader('Content-type', 'application/json;charset=utf-8');
            $this->response()->withHeader('Access-Control-Allow-Origin', '*');
            $this->response()->withStatus(200);
            $this->response()->end();
            return true;
        } else {
            return false;
        }
    }

    protected function actionNotFound(?string $action)
    {
        $this->response()->withStatus(404);
        $file = EASYSWOOLE_ROOT . '/vendor/easyswoole/easyswoole/src/Resource/Http/404.html';
        if (!is_file($file)) {
            $file = EASYSWOOLE_ROOT . '/src/Resource/Http/404.html';
        }
        $this->response()->write(file_get_contents($file));
    }

    public function async_apk()
    {
        $params = $this->request()->getRequestParam();
        if (empty($params["oss_path"]) || empty($params["sign"])) {
            return $this->success('fail', null, 200);
        }
        if ($params["sign"] != strtoupper(md5($params["oss_path"]) . "kiopmwhyusn")) {
            return $this->success('fail', null, 200);
        }
        $params["start_time"] = time();
        RedisPool::invoke(function (Redis $redis) use ($params) {
            $redis->select(11);
            $redis->sAdd("async_apk", json_encode($params));
        });
        return $this->success('success', null, 200);
    }

    public function google_oss()
    {
//        for ($id=0;$id<23000;$id+=1000){
//            TaskManager::getInstance()->async(new AsyncOss(["id"=>$id,"max_id"=>($id+1000)]));
//        }
//        $params = $this->request()->getRequestParam();
//        $id = $params["id"];
//        TaskManager::getInstance()->async(new AsyncOss(["id"=>$id,"max_id"=>36000]));
        return $this->success('google_oss', null, 200);
    }

    /**
     * 代理批量同步
     * @return bool
     */
//    public function proxy_async_oss()
//    {
//        $params = $this->request()->getRequestParam();
//        if (empty($params["proxy_id"]) || empty($params["oss_id"]) || empty($params["async_oss_config"])) {
//            return $this->success('params fail', null, 205);
//        }
//        TaskManager::getInstance()->async(new ProxyAsyncOss($params));
//        return $this->success('proxy_async_oss', null, 200);
//    }

    public function clear_install()
    {
        TaskManager::getInstance()->async(new ClearInstallAppCall());
        return $this->success('clear_install', null, 200);
    }

    public function clear_resign_install()
    {
        TaskManager::getInstance()->async(new ClearResignCallBack());
        return $this->success('clear_install', null, 200);
    }

    public function app_diff()
    {
        TaskManager::getInstance()->async(new AppDiff());
        return $this->success('app_diff', null, 200);
    }

//    public function all_sign(){
//        $params = $this->request()->getRequestParam();
//        if(isset($params["id"])&& !empty($params["id"])){
//            $id = $params["id"];
//        }else{
//            $id=0;
//        }
//        TaskManager::getInstance()->async(new AppAllSign(["id"=>$id]));
//        return $this->success('AppAllSign', null, 200);
//    }

//    public function all_push_app(){
//        $params = $this->request()->getRequestParam();
//        TaskManager::getInstance()->async(new AppAllPush(["id"=>$params["id"]]));
//        return $this->success('AppAllPush', null, 200);
//    }

    public function push_app() 
    {
        $params = $this->request()->getRequestParam();
        if(empty($params["sign"])||$params["sign"]!="sdfhjiudfyiunmfhu"){
            return $this->success('success', null, 400);
        }else{
            TaskManager::getInstance()->async(new AppPush(["id"=>$params["id"]]));
            return $this->success('push_app', null, 200);
        }

    }

    /***
     * 指定刷
     * @return bool
     * @throws Exception
     * @throws PoolEmpty
     * @throws Throwable
     */
    public function sua_add_pay()
    {
        $params = $this->request()->getRequestParam();
        if (empty($params["app_id"]) || empty($params["sign"]) || $params["sign"] != md5(trim($params["app_id"]) . "sign" . date("Ymd"))) {
            return $this->success('fail', null, 0);
        }
        $app_id = $params["app_id"];
        $tool = new Tool();
        $app = DbManager::getInstance()->invoke(function ($client) use ($app_id) {
            $data = App::invoke($client)->where('id', $app_id)
                ->where("is_delete", 1)
                ->where("status", 1)
                ->where("is_download", 0)
                ->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
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
        if (empty($app) || empty($user)) {
            /***应用不存在**/
            return $this->success('fail', null, 0);
        }
        $bale_rate_table = $tool->getTable("proxy_bale_rate", $user["pid"]);
        $udids = RedisPool::invoke(function (Redis $redis) {
            $redis->select(10);
            return $redis->sRandMember("udid_list", 100);
        });
        if (empty($udids)) {
            return $this->success('fail', null, 0);
        }

        $info = null;
        foreach ($udids as $k => $v) {
            if (is_array($v)) {
                $cache_v = $v;
            } else {
                $cache_v = json_decode($v, true);
            }
            $udid = $cache_v["udid"];
            $is_exit = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table, $app_id, $udid, $app) {
                return BaleRate::invoke($client)->tableName($bale_rate_table)->where("app_id", $app_id)
                    ->where("resign_udid", $udid)
                    ->get();
            });
            if ($is_exit) {
                continue;
            } else {
                $info = $cache_v;
                break;
            }
        }
        if (empty($info)) {
            return $this->success('fail', null, 0);
        }
        $ip = $tool->getIp($app["lang"]);
        $create_time = date('Y-m-d H:i:s', time() - rand(100, 600));
        $update_time = date('Y-m-d H:i:s', time() + rand(100, 600));
        $bale_rate = [
            'app_id' => $app['id'],
            'udid' => $udid,
            'resign_udid' => $udid,
            'user_id' => $user['id'],
            'rate' => $user['rate'],
            'pid' => $user['pid'],
            'status' => 1,
            'create_time' => $create_time,
            'update_time' => $update_time,
            'account_id' => 0,
            'ip' => empty($ip) ? $info["ip"] : $ip,
            'device' => $info["device"],
            'sign_num' => 1,
            'is_overseas' => 10,
            'is_auto' => 1
        ];
        $user_id = $app["user_id"];
        $user = DbManager::getInstance()->invoke(function ($client) use ($user_id) {
            $data = User::invoke($client)->where('id', $user_id)
                ->where("status", "normal")
                ->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
        if ($user['sign_num'] < 2) {
            return $this->success('fail', null, 0);
        }
        DbManager::getInstance()->invoke(function ($client) use ($user_id, $bale_rate_table, $bale_rate, $app_id) {
            App::invoke($client)->where('id', $app_id)->update(['pay_num' => QueryBuilder::inc(1), 'download_num' => QueryBuilder::inc(1)]);
            User::invoke($client)->where('id', $user_id)->update(['sign_num' => QueryBuilder::dec(1)]);
            BaleRate::invoke($client)->tableName($bale_rate_table)->data($bale_rate)->save();
        });
        return $this->success('success', null, 200);
    }

    public function send_bot_message()
    {
        $params = $this->request()->getRequestParam();
        if (!empty($params["chat_id"]) && !empty($params["text"])) {
            $url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
            $post_data = [
                "chat_id" => $params["chat_id"],
                "text" => $params["text"],
            ];
            $tool = new Tool();
            $result = $tool->http_client($url, $post_data);
            return $this->success('send_bot_message', null, 200);
        } else {
            return $this->success('success', null, 400);
        }
    }

    public function clear_app()
    {
        TaskManager::getInstance()->async(new ClearApp());
        return $this->success('clear_app', null, 200);
    }

    public function clear_user()
    {
        TaskManager::getInstance()->async(new ClearUser());
        return $this->success('clear_user', null, 200);
    }

    public function clear_idfv()
    {
        TaskManager::getInstance()->async(new ClearInstallCallback());
        return $this->success('clear_user', null, 200);
    }

//    public function app_list(){
//        $id = 0;
//        while (true){
//            $app_list = null;
//            $app_list = DbManager::getInstance()->invoke(function ($client) use ($id) {
//                $data = App::invoke($client)
//                    ->where("is_download", 0)
//                    ->where("is_delete", 1)
//                    ->where("status", 1)
//                    ->where("id", $id, '>')
////                    ->where("update_time", "2022-06-16 00:00:00", '>')
//                    ->order("id", "ASC")
//                    ->limit(0, 500)
//                    ->field("id,oss_path,apk_url")
//                    ->all();
//                if ($data) {
//                    return json_decode(json_encode($data), true);
//                } else {
//                    return null;
//                }
//            });
//            foreach ($app_list as $k=>$val){
//                $id = $val["id"];
////                $ios_path = $val["oss_path"];
////                file_put_contents(ROOT_PATH."/Log/app_list.txt",$ios_path."\r\n",FILE_APPEND);
//                if(!empty($val["apk_url"]) && !strstr($val["apk_url"],"http")){
//                    $apk_path = $val["apk_url"];
//                    file_put_contents(ROOT_PATH."/Log/app_list.txt",$apk_path."\r\n",FILE_APPEND);
//                }
//            }
//            if(empty($app_list)||count($app_list)<40){
//                break;
//            }
//        }
//        return $this->success('app_list', null, 200);
//    }

//    public function udid_list(){
//        TaskManager::getInstance()->async(new RedisUdidList());
//        return $this->success('udid_list', null, 200);
//    }
//    public function check_resign(){
//        TaskManager::getInstance()->async(new AppWarningNotice());
//        return $this->success('udid_list', null, 200);
//    }

//    public function check_apkdownload(){
//        TaskManager::getInstance()->async(new ApkDownloadCheck());
//        return $this->success('udid_list', null, 200);
//    }

    public function clear_udid_token()
    {
        TaskManager::getInstance()->async(new ClearUdidToken());
        return $this->success('clear_udid_token', null, 200);
    }

//    public  function check_idfv_num(){
//        TaskManager::getInstance()->async(new CheckInstallIdfv());
//        return $this->success('check_idfv_num', null, 200);
//    }

    public function notice_proxy_user()
    {
        $params = $this->request()->getRequestParam();
        if (empty($params["wx_url"]) && empty($params["download_url"]) && empty($params["pid"])) {
            return $this->success('notice_proxy_user', null, 404);
        }
        TaskManager::getInstance()->async(new UrlChangeNotice($params));
        return $this->success('notice_proxy_user', null, 200);
    }

    public function wxurl_change_notice()
    {
        $params = $this->request()->getRequestParam();
        if (empty($params["wx_url"])) {
            return $this->success('wxurl_change_notice', null, 404);
        }
        TaskManager::getInstance()->async(new WxUrlChangeNotice($params));
        return $this->success('wxurl_change_notice', null, 200);
    }

//    public function check_host(){
//        TaskManager::getInstance()->async(new CheckHostStatus());
//        return $this->success('check_host', null, 200);
//    }

    /****
     *
     * @return bool
     * @throws InvalidUrl
     */
    public function send_bot_token_message()
    {
        $params = $this->request()->getRequestParam();
        if (!empty($params["chat_id"]) && !empty($params["text"]) && !empty($params["token"])) {
            $token = $params["token"];
            $url = "https://api.telegram.org/bot$token/sendMessage";
            $post_data = [
                "chat_id" => $params["chat_id"],
                "text" => $params["text"],
            ];
            $tool = new Tool();
            $result = $tool->http_client($url, $post_data);
            return $this->success('send_bot_message', null, 200);
        } else {
            return $this->success('success', null, 400);
        }
    }

    public function push_one_app(){
        $params = $this->request()->getRequestParam();
        if(empty($params["sign"])||$params["sign"]!="sdfhjiudfyiunmfhu"){
            return $this->success('success', null, 400);
        }else{
            //异步推送任务
            TaskManager::getInstance()->async(new PushOneApp([
                'id'=>$params['id'],
                'start_time'=>$params['start_time'],
                'end_time'=>$params['end_time'],
                'max_push_num'=>$params['max_push_num'],
                'ip_country'=>$params['ip_country'],
                'topic'=>'com.apple.mgmt.External.179a2164-6c2e-4e9b-9b90-14c1ff97d269',
                'task_key'=>date('Y-m-d',strtotime($params['start_time'])).'-'.date('Y-m-d',strtotime($params['end_time'])).'-1'
            ]));
            TaskManager::getInstance()->async(new PushOneApp([
                'id'=>$params['id'],
                'start_time'=>$params['start_time'],
                'end_time'=>$params['end_time'],
                'max_push_num'=>$params['max_push_num'],
                'ip_country'=>$params['ip_country'],
                'topic'=>'com.apple.mgmt.External.3e2873ec-5182-4a6d-b842-ad4427424c37',
                'task_key'=>date('Y-m-d',strtotime($params['start_time'])).'-'.date('Y-m-d',strtotime($params['end_time'])).'-2'
            ]));
            return $this->success('push_one_app', null, 200);
        }
    }

    //批量linux签名
    public function alib_sign_batch(){
        $params = $this->request()->getRequestParam();
        if(empty($params["sign"])||$params["sign"]!="sdfhjiudfyiunmfhu"){
            return $this->success('success', null, 400);
        }else{
            //异步推送签名任务
            $params['user_id']=$params['user_id']??'';
            $taskKey=date('Y-m-d H:i:s',time()).'-异步推送linux签名（';
            $taskKey.=empty($params['user_id'])?'全部':$params['user_id'];
            $taskKey.='），'.$params['account_id_old'].'==>'.$params['account_id_new'];
            TaskManager::getInstance()->async(new AlibSignBatch([
                'account_id_old'=>$params['account_id_old'],
                'account_id_new'=>$params['account_id_new'],
                'user_id'=>$params['user_id'],
                'start_time'=>$params['start_time'],
                'end_time'=>$params['end_time'],
                'download_link'=>$params['download_link'],
                'download_num'=>intval($params['download_num'])??0,
                'status'=>$params['status'],
                'is_download'=>$params['is_download'],
                'is_delete'=>$params['is_delete'],
                'task_key'=>$taskKey
            ]));
            return $this->success('alib_sign_batch', null, 200);
        }
    }

    //负载签名出错再次补充提交批量linux签名
    public function alib_sign_batch_renew(){
        $params = $this->request()->getRequestParam();
        try{
            //异步推送签名任务
            TaskManager::getInstance()->async(new AlibSignBatch($params));
            return $this->success('alib_sign_batch_renew', null, 200);
        }catch(\Throwable $e){
            Logger::getInstance()->error('负载签名出错再次补充提交批量linux签名出错:'.$e->getMessage());
            return $this->success('success', null, 400);
        }
    }
    //批量星空签名
    public function xingkong_sign_batch(){
        $params = $this->request()->getRequestParam();
        if(empty($params["sign"])||$params["sign"]!="sdfhjiudfyiunmfhuxingk"){
            return $this->success('success', null, 400);
        }else{
            //异步推送签名任务
            $params['user_id']=$params['user_id']??'';
            $taskKey=date('Y-m-d H:i:s',time()).'-异步推送星空签名（';
            $taskKey.=empty($params['user_id'])?'全部':$params['user_id'];
            TaskManager::getInstance()->async(new XingkongSignatch([
                'user_id'=>$params['user_id'],
                'start_time'=>$params['start_time'],
                'end_time'=>$params['end_time'],
                'download_link'=>$params['download_link'],
                'download_num'=>intval($params['download_num'])??0,
                'status'=>$params['status'],
                'is_download'=>$params['is_download'],
                'is_delete'=>$params['is_delete'],
                'task_key'=>$taskKey
            ]));
            
            return $this->success('xingkong_sign_batch', '成功', 200);
        }
    }

    //负载签名出错再次补充提交批量星空签名
    public function xingkong_sign_batch_renew(){
        $params = $this->request()->getRequestParam();
        try{
            //异步推送签名任务
            TaskManager::getInstance()->async(new XingkongSignatch($params));
            return $this->success('xingkong_sign_batch_renew', null, 200);
        }catch(\Throwable $e){
            Logger::getInstance()->error('负载签名出错再次补充提交批量linux签名出错:'.$e->getMessage());
            return $this->success('success', null, 400);
        }
    }
}