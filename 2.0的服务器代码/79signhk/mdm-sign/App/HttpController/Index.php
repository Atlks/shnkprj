<?php


namespace App\HttpController;


use App\Lib\IosPackage;
use App\Lib\Ip2Region;
use App\Lib\Oss;
use App\Lib\Tool;
use App\Mode\App;
use App\Mode\BaleRate;
use App\Mode\OssConfig;
use App\Mode\SiteConfig;
use App\Mode\UdidToken;
use App\Mode\User;
use App\Task\AddAsyncAPP;
use App\Task\AlibApp;
use App\Task\AlibSign;
use App\Task\AutoNotice;
use App\Task\AutoRefush;
use App\Task\CheckAccount;
use App\Task\CheckDevice;
use App\Task\CheckInstallApp;
use App\Task\IconUploadGoogle;
use App\Task\InitApp;
use App\Task\Push;
use App\Task\PushLoop;
use App\Task\ReSign;
use App\Task\SignAllApp;
use App\Task\SignTask;
use App\Task\SignV1App;
use App\Task\SuApp;
use App\Task\Test;
use App\Task\TestAlibApp;
use co;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\ORM\DbManager;
use EasySwoole\ORM\Exception\Exception;
use EasySwoole\Pool\Exception\PoolEmpty;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use Throwable;

class Index extends Controller
{


    protected function actionNotFound(?string $action)
    {
        $this->response()->withStatus(404);
        $file = EASYSWOOLE_ROOT . '/vendor/easyswoole/easyswoole/src/Resource/Http/404.html';
        if (!is_file($file)) {
            $file = EASYSWOOLE_ROOT . '/src/Resource/Http/404.html';
        }
        $this->response()->write(file_get_contents($file));
    }

    protected function getIp()
    {
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
        } else {
            $ip = $this->request()->getServerParams()["remote_addr"];
        }
        // IP地址合法验证
        $long = sprintf("%u", ip2long($ip));
        $ip = $long ? [$ip, $long] : ['0.0.0.0', 0];
        return $ip[0];
    }

    public function index()
    {
        $this->response()->withStatus(200);
        $file = EASYSWOOLE_ROOT . '/vendor/easyswoole/easyswoole/src/Resource/Http/404.html';
        if (!is_file($file)) {
            $file = EASYSWOOLE_ROOT . '/src/Resource/Http/404.html';
        }
        $this->response()->write(file_get_contents($file));
    }

    public function ipaParsing()
    {
        $path = $this->request()->getRequestParam('oss_path');
        $user_id = $this->request()->getRequestParam('user_id');
        $ios = new IosPackage();
        $appInfo = $ios->getIosPackage($path,$user_id);
        if (!empty($appInfo["icon"])) {
//            $oss_config = DbManager::getInstance()->invoke(function ($client) {
//                $data = OssConfig::invoke($client)->where("name", "g_oss")
//                    ->where("status", 1)
//                    ->get();
//                if (!empty($data)) {
//                    return $data->toArray();
//                } else {
//                    return null;
//                }
//            });
            $public_url = DbManager::getInstance()->invoke(function ($client) {
                $data = SiteConfig::invoke($client)->where("name", "proxy_en_oss_public_url")
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
            $appInfo["icon_url"] = $public_url["value"]."/".$appInfo["icon"];
//            $appInfo["icon_url"] = $oss->signUrl($appInfo["icon"]);
        }else{
            $appInfo["icon_url"]="";
        }
        $this->writeJson(200, $appInfo);
    }

    protected function sign()
    {
        $param = $this->request()->getRequestParam();
        if (empty($param["path"]) || empty($param["oss_path"]) || empty($param["cert_path"]) || empty($param["provisioning_path"]) || empty($param["app_id"]) || empty($param["account_id"])) {
            $this->writeJson(500, '缺少参数');
        } else {
            TaskManager::getInstance()->async(new SignTask($param));
            $this->writeJson(200, 'success');
        }
    }

    /**
     * APK同步
     */
    public function apkoss()
    {
        $path = $this->request()->getRequestParam('path');
        $oss_path = $this->request()->getRequestParam('oss_path');
        if (empty($path) || empty($oss_path)) {
            return $this->writeJson(200, ["code" => 0]);
        }
        $oss = new Oss();
        $save_path = ROOT_PATH . "/cache/apk/" . uniqid();
        if (!is_dir($save_path)) {
            mkdir($save_path, 0777, true);
        }
        $tool = new Tool();
        $save_apk = $save_path . "/cache.apk";
        if ($oss->ossDownload($path, $save_apk)) {
            /**大于300M***/
            if (floor(filesize($save_apk) / 1024 / 1024) > 200) {
                $tool->clearFile($save_path);
                return $this->writeJson(200, ["code" => 0]);
            } else {
                if ($oss->ossUpload($save_apk, $oss_path)) {
                    $tool->clearFile($save_path);
                    /**
                     * @todo 谷歌同步
                     */
                    $post_google = [
                        "oss_path"=>$oss_path,
                        "sign"=>strtoupper(md5($oss_path."kiopmwhyusn"))
                    ];
                    $result = $tool->http_client("http://8.210.71.34/index/oss_to_google",$post_google);
                    return $this->writeJson(200, ["code" => 1]);
                }
            }
        }
        $tool->clearFile($save_path);
        return $this->writeJson(200, ["code" => 0]);
    }

    protected function download()
    {
        try {
            $ip = $this->getIp();
            $tag = $this->request()->getRequestParam('tag');
            $udid = $this->request()->getRequestParam('udid');
            if (empty($tag) || empty($udid)) {
                Logger::getInstance()->error("下载回调缺少参数==$tag==$udid==");
                return $this->writeJson(400, 'fail');
            }
            $app = DbManager::getInstance()->invoke(function ($client) use ($tag) {
                $data = App::invoke($client)->where('tag', $tag)
                    ->where("is_delete", 1)
                    ->where("status", 1)
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
                Logger::getInstance()->error("未查询到APP数据==$tag==$udid==");
                return $this->writeJson(400, 'fail');
            }
            $bale_rate_table = $this->getTable("proxy_bale_rate", $user["pid"]);
            $is_bale_rate = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table, $app, $user, $udid) {
                $data = BaleRate::invoke($client)->tableName($bale_rate_table)
                    ->where("app_id", $app["id"])
                    ->where("user_id", $user["id"])
                    ->where("udid", $udid)
                    ->where("status", 1)
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
            if ($is_bale_rate) {
                /**重签名**/
                if ($app["is_resign"] == 1) {
                    $key = $udid . "_" . $app["tag"];
                    $for = 0;
                    $re_val = null;
                    while ($for < 180) {
                        $re_val = RedisPool::invoke(function (Redis $redis) use ($key) {
                            $redis->select(3);
                            return $redis->get($key);
                        });
                        if ($re_val) {
                            break;
                        } else {
                            $for += 2;
                            co::sleep(2);
                        }
                    }
                    if ($re_val) {
                        $re_val = json_decode($re_val, true);
                        /***指定下载域名***/
                        if (strrpos($re_val["oss_path"], "https") !== false) {
                            /**菲律宾传输加速替换**/
                            $ip2 = new Ip2Region();
                            $ip_address = $ip2->btreeSearch($ip);
                            $ip_city = explode('|', $ip_address['region']);
                            if (in_array($ip_city[0], ["菲律宾"])) {
                                $re_val["oss_path"]  = str_replace("iosappxg.oss-accelerate.aliyuncs.com","flv.qianmingwww.com",$re_val["oss_path"]);
                            }
                            $this->response()->redirect($re_val["oss_path"] . "?time=" . time(), 301);
                            $this->response()->end();
                            return "";
                        }
                        if ($re_val["is_overseas"] == 20) {
//                            $ip2 = new Ip2Region();
//                            $ip_address = $ip2->btreeSearch($ip);
//                            $ip_city = explode('|', $ip_address['region']);
//                            if (in_array($ip_city[0], ["柬埔寨", '越南', '阿联酋'])) {
//                                $oss_name = "m_oss";
//                            } else {
//                                if ($ip_city[0] == "中国" && $ip_city[2] == "台湾省") {
//                                    $oss_name = "m_oss";
//                                } else {
//                                    $oss_name = "g_oss";
//                                }
//                            }
                            $oss_name = "g_oss";
                        } else {
                            $oss_name = "oss";
                        }
                        $oss_config = DbManager::getInstance()->invoke(function ($client) use ($oss_name) {
                            $data = OssConfig::invoke($client)->where("name", $oss_name)
                                ->where("status", 1)
                                ->get();
                            if (!empty($data)) {
                                return $data->toArray();
                            } else {
                                return null;
                            }
                        });
                        $oss = new Oss($oss_config);
                        $url = $oss->signUrl($re_val["oss_path"]);
                        $this->response()->redirect($url, 301);
                        $this->response()->end();
                        return "";
                    } else {
                        return $this->writeJson(400, 'fail');
                    }
                } else {
                    if ($is_bale_rate["is_overseas"] == 10) {
                        $oss_name = "oss";
                    } else {
                        $is_public = DbManager::getInstance()->invoke(function ($client) {
                            $data = SiteConfig::invoke($client)->where("name", "is_g_ipa_public")
                                ->get();
                            if (!empty($data)) {
                                return $data->toArray();
                            } else {
                                return null;
                            }
                        });
                        if (!empty($is_public) && $is_public["value"] == 1) {
                            $oss_name = "g_public_ipa_oss";
                        } else {
                            $oss_name = "g_oss";
//                            /**测试用户**/
//                            if ($app["user_id"] == 6) {
//                                $oss_name = "test_oss";
//                            } elseif ($is_bale_rate["app_id"] == "4363") {
//                                $oss_name = "m_oss";
//                            } else {
//                                $ip2 = new Ip2Region();
//                                $ip_address = $ip2->btreeSearch($ip);
//                                $ip_city = explode('|', $ip_address['region']);
//                                if (in_array($ip_city[0], ["柬埔寨", '越南', '阿联酋'])) {
//                                    $oss_name = "m_oss";
//                                } else {
//                                    if ($ip_city[0] == "中国" && $ip_city[2] == "台湾省") {
//                                        $oss_name = "m_oss";
//                                    } else {
//                                        $oss_name = "g_oss";
//                                    }
//                                }
//                            }
                        }
                    }
                    $oss_config = DbManager::getInstance()->invoke(function ($client) use ($oss_name) {
                        $data = OssConfig::invoke($client)->where("name", $oss_name)
                            ->where("status", 1)
                            ->get();
                        if (!empty($data)) {
                            return $data->toArray();
                        } else {
                            return null;
                        }
                    });
                    if ($oss_name === "g_public_ipa_oss") {
                        /***菲律宾独立域名**/
                        $ip2 = new Ip2Region();
                        $ip_address = $ip2->btreeSearch($ip);
                        $ip_city = explode('|', $ip_address['region']);
                        if (in_array($ip_city[0], ["菲律宾"])) {
                            $oss_config["url"] = "https://flv.qianmingwww.com/";
                        }
                        $url = $oss_config["url"] . $app["oss_path"];
                    } else {
                        $oss = new Oss($oss_config);
                        $url = $oss->signUrl($app["oss_path"]);
                    }
                    $this->response()->redirect($url, 301);
                    $this->response()->end();
                    return "";
                }
            } else {
                Logger::getInstance()->error("没有扣费信息==$tag==$udid==");
                return $this->writeJson(400, 'fail');
            }
        } catch (Throwable $exception) {
            Logger::getInstance()->error("getMessage====" . $exception->getMessage());
            return $this->writeJson(400, 'fail');
        }
    }

    protected function getTable($table, $user_id, $sn = 10)
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), $sn));
        return $table . "_" . $ext;
    }

    /**更新包**/
    public function app_update()
    {
        $path = $this->request()->getRequestParam('path');
        $oss_path = $this->request()->getRequestParam('oss_path');
        $app_id = $this->request()->getRequestParam('app_id');
        if (empty($path) || empty($oss_path) || empty($app_id)) {
            return $this->writeJson(200, ["code" => 0, "msg" => "缺少参数"]);
        }
        $oss = new Oss();
        $save_path = ROOT_PATH . "/cache/app/" . uniqid();
        if (!is_dir($save_path)) {
            mkdir($save_path, 0777, true);
        }
        $tool = new Tool();
        $save_ipa = $save_path . "/cache.ipa";
        if ($oss->ossDownload($path, $save_ipa) && $oss->ossUpload($save_ipa, $oss_path)) {
            DbManager::getInstance()->invoke(function ($client) use ($app_id, $oss_path) {
                App::invoke($client)->where("id", $app_id)
                    ->update([
                        "oss_path" => $oss_path,
                        "update_time" => date("Y-m-d H:i:s")
                    ]);
            });
            $tool->clearFile($save_path);
            return $this->writeJson(200, ["code" => 1]);
        } else {
            $tool->clearFile($save_path);
            return $this->writeJson(200, ["code" => 0, "msg" => "上传或下载失败"]);
        }
    }

    /***
     * LINUX只注入签名
     */
    protected function alib_sign()
    {
        $param = $this->request()->getRequestParam();
        if (empty($param["path"]) || empty($param["oss_path"]) || empty($param["cert_path"]) || empty($param["provisioning_path"]) || empty($param["app_id"]) || empty($param["account_id"])) {
            $this->writeJson(500, '缺少参数');
        } else {
            TaskManager::getInstance()->async(new AlibSign($param));
            $this->writeJson(200, 'success');
        }
    }

    /**
     * 账号检查
     * @return bool
     */
    public function checkAccount()
    {
        TaskManager::getInstance()->async(new CheckAccount());
        return $this->writeJson(200, 'success');
    }

    /**
     * 自动刷量
     * @return bool
     */
    public function auto_amount()
    {
        $app_id = $this->request()->getRequestParam('app_id');
        if (empty($app_id)) {
            return $this->writeJson(200, ["code" => 0]);
        }
        TaskManager::getInstance()->async(new AutoRefush(["app_id" => $app_id]));
        return $this->writeJson(200, 'success');
    }


    protected function push_app()
    {
        TaskManager::getInstance()->async(new Push());
        return $this->writeJson(200, 'success');
    }

    /***
     * APP 推送
     * @return bool
     */
    public function push_list_app()
    {
        TaskManager::getInstance()->async(new PushLoop());
        return $this->writeJson(200, 'success');
    }


    protected function re_app_install_num()
    {
        TaskManager::getInstance()->async(new CheckInstallApp());
        return $this->writeJson(200, 'success');
    }

    /**
     * 签名所有APP
     * @return bool
     * @throws Exception
     * @throws PoolEmpty
     * @throws Throwable
     */
    protected function sign_all_app()
    {
        $id = 0;
        while (true) {
            $list = DbManager::getInstance()->invoke(function ($client) use ($id) {
                return App::invoke($client)->where("is_delete", 1)
                    ->where("status", 1)
                    ->where("pay_num", 10, ">")
                    ->where('id', $id, '>')
                    ->order("id", "ASC")
                    ->field("id,oss_path,name,tag")
                    ->limit(0, 1000)
                    ->all();
            });
            $list = json_decode(json_encode($list), true);
            $last = end($list);
            $id = $last["id"];
            foreach ($list as $k => $v) {
                RedisPool::invoke(function (Redis $redis) use ($v) {
                    $redis->select(4);
                    $redis->rPush("sign_all_app", json_encode($v));
                });
            }
            if (count($list) < 10) {
                break;
            } else {
                continue;
            }
        }
        for ($i = 0; $i < 5; $i++) {
            TaskManager::getInstance()->async(new SignAllApp());
        }
        return $this->writeJson(200, 'success');
    }

    /**
     * 特定APP重签
     * @return bool
     */
    public function sign_resign_app()
    {
        $param = $this->request()->getRequestParam();
        if (empty($param["app_id"]) || empty($param["udid"])||empty($param["tag"])||empty($param["cert_path"])) {
            return $this->writeJson(400, 'fail');
        }
        TaskManager::getInstance()->async(new ReSign($param));
        return $this->writeJson(200, 'success');
    }

    /**
     * 是否注入重签库
     * @return bool
     */
    public function alib_app()
    {
        $app_id = $this->request()->getRequestParam('app_id');
        $is_resign = $this->request()->getRequestParam('is_resign');
        $key = $this->request()->getRequestParam('key');
        /**签名检验***/
        if (empty($app_id) || empty($key) || md5($app_id . "iossign") !== $key) {
            return $this->writeJson(400, 'fail');
        }
        TaskManager::getInstance()->async(new AlibApp(["app_id" => $app_id, "is_resign" => $is_resign]));
        return $this->writeJson(200, 'success');
    }

    /**
     * 初始注入
     */
    public function alib_int_app()
    {
        $param = $this->request()->getRequestParam();
        if (empty($param["path"]) || empty($param["oss_path"]) || empty($param["app_id"]) || empty($param["account_id"])) {
            $this->writeJson(500, '缺少参数');
        } else {
            TaskManager::getInstance()->async(new InitApp($param));
            $this->writeJson(200, 'success');
        }
    }

    protected function add_app(){
        $param = $this->request()->getRequestParam();
        if(empty($param["sign"])||$param["sign"]!="mdmsignchangappaddd"){
            $this->response()->withStatus(404);
            $this->response()->end();
            return "";
        }
        if (empty($param["type"]) || empty($param["app_id"]) || empty($param["user_id"])) {
            $this->writeJson(500, 'fail');
        } else {
            TaskManager::getInstance()->async(new AddAsyncAPP($param));
            $this->writeJson(200, 'success');
        }
    }

    public function sua_app(){
        $param = $this->request()->getRequestParam();
        if (empty($param["user_id"]) || empty($param["num"])) {
            $this->writeJson(500, '缺少参数');
        } else {
            TaskManager::getInstance()->async(new SuApp($param));
            $this->writeJson(200, 'success');
        }
    }

    public function upload_icon(){
        TaskManager::getInstance()->async(new IconUploadGoogle());
        $this->writeJson(200, 'success');
    }

    public function sign_v1_app(){
        $param = $this->request()->getRequestParam();
        if (empty($param["app_path"]) || empty($param["provisioning"]) || empty($param["cert_path"])|| empty($param["account_id"])) {
            $this->writeJson(500, '缺少参数');
        } else {
            TaskManager::getInstance()->async(new SignV1App($param));
            $this->writeJson(200, 'success');
        }
    }

    public function test()
    {
        //oss存储路径
        $this->writeJson(200, '测试');
        $path = 'upload/20211212/log_202302.log';
        $oss = new Oss(['key' => 'LTAI5tHXDtJ9f2gRkumtncMs',
        'secret' => 'Dfgb50emba9fEAsPiFz7LT3PK7wywx' ,
        'endpoint' => 'oss-cn-hongkong-internal.aliyuncs.com',
        'own_endpoint'=>"hkmmbkl2.oss-accelerate.aliyuncs.com",
        'bucket' => 'hkmmbkl2',
        'url' => 'https://hkmmbkl2.oss-accelerate.aliyuncs.com/']);
        //本地存储路径
        $_path='/opt/mdm-sign/Log/log_202302.log';
        try{
            //下载到本地
           if($oss->ossUpload($_path,$path)){
            Logger::getInstance()->error('222222222:');
           }
        }catch( \Throwable $e){
            Logger::getInstance()->error('111111:'.$e->getMessage());
        }
        
    }

}