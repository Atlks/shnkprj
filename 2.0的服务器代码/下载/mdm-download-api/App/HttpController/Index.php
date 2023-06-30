<?php


namespace App\HttpController;


use App\Lib\Ip2Region;
use App\Lib\Oss;
use App\Lib\RedisLib;
use App\Model\App;
use App\Model\BaleRate;
use App\Model\Enterprise;
use App\Model\OssConfig;
use App\Model\ProxyUser;
use App\Model\SiteConfig;
use App\Task\AppWarningNotice;
use App\Task\CheckAccount;
use App\Task\CheckInstallApp;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use Swoole\Coroutine;

class Index extends Controller
{

    /**
     * 回调
     * @param array $data
     * @return bool
     */
    protected function success($data = [])
    {
        if (!$this->response()->isEndResponse()) {
            $this->response()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->response()->withHeader('Content-type', 'application/json;charset=utf-8');
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
        $file = EASYSWOOLE_ROOT.'/vendor/easyswoole/easyswoole/src/Resource/Http/404.html';
        if(!is_file($file)){
            $file = EASYSWOOLE_ROOT.'/src/Resource/Http/404.html';
        }
        $this->response()->write(file_get_contents($file));
    }

    public function index()
    {
        $this->response()->withStatus(200);
        $this->response()->end();
    }

    public function key_redis(){
        $udid = $this->request()->getRequestParam("udid");
        $oss_path = $this->request()->getRequestParam("oss_path");
        $tag = $this->request()->getRequestParam("tag");
        $is_overseas = $this->request()->getRequestParam("is_overseas");
        if(empty($udid)||empty($oss_path)||empty($tag)||empty($is_overseas)){
            return $this->writeJson(500);
        }else{
            $key = $udid . "_" . $tag;
            $value = [
                "oss_path" => $oss_path,
                "is_overseas" => $is_overseas,
                'time'=>time()
            ];
            RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($key, $value) {
                $redis->select(3);
                $redis->set($key, json_encode($value), 600);
            });
            return $this->writeJson(200);
        }
    }

    protected function get_sign_data(){
        $app_id = $this->request()->getRequestParam("app_id");
        if(empty($app_id)){
           return $this->writeJson(200,["code"=>0]);
        }
        $app = RedisLib::get("app_id:".$app_id,4);
        if(empty($app)) {
            $app = DbManager::getInstance()->invoke(function ($client) use ($app_id) {
                $data = App::invoke($client)->where("id", $app_id)
                    ->where("status", 1)
                    ->where("is_delete", 1)
                    ->where("is_download", 0)
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
            RedisLib::set("app_id:".$app_id,$app,4,600);
        }
        if (empty($app)) {
            return true;
        }
        $account = RedisLib::get("account",4);
        if(empty($account)) {
            $account = DbManager::getInstance()->invoke(function ($client) {
                $data = Enterprise::invoke($client)->where('status', 1)->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
            RedisLib::set("account",$account,4,600);
        }
        $result = [
            "code"=>200,
            "app_path"=>$app["oss_path"],
            "package_name"=>$app["package_name"],
            "tag"=>$app["tag"],
            "cert_path"=>$account["oss_path"],
            "oss_provisioning"=>$account["oss_provisioning"],
            "password"=>$account["password"],
        ];
        $this->writeJson(200,$result);
    }

    protected function getTable($table, $user_id, $sn = 10)
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), $sn));
        return $table . "_" . $ext;
    }


    protected function getIp(){
        $server = $this->request()->getHeaders();
        if (isset($server['x-forwarded-for'])) {
            $arr = explode(',', $server['x-forwarded-for'][0]);
            $pos = array_search('unknown', $arr);
            if (false !== $pos) {
                unset($arr[$pos]);
            }
            $ip = trim(current($arr));
        } elseif (isset($server['client-ip'])) {
            $ip = $server['client-ip'][0];
        } elseif (isset($server["x-real-ip"])) {
            $ip = $server["x-real-ip"][0];
        }else{
            $ip = $this->request()->getServerParams()["remote_addr"];
        }
        // IP地址合法验证
        $long = sprintf("%u", ip2long($ip));
        $ip   = $long ? [$ip, $long] : ['0.0.0.0', 0];
        return $ip[0];
    }

    protected function app_waring(){
        TaskManager::getInstance()->async(new AppWarningNotice());
       echo "success";
    }




}