<?php


namespace App\HttpController;


use App\Lib\GoogleOss;
use App\Lib\Ip2Region;
use App\Lib\Oss;
use App\Lib\RedisLib;
use App\Lib\Tool;
use App\Mode\App;
use App\Mode\AppInstallCallback;
use App\Mode\AppInstallError;
use App\Mode\BaleRate;
use App\Mode\Enterprise;
use App\Mode\OssConfig;
use App\Mode\ProxyDomain;
use App\Mode\ProxyStyle;
use App\Mode\ProxyUserDomain;
use App\Mode\SiteConfig;
use App\Mode\User;
use App\Mode\UserIdfv;
use App\Task\AppDownloadCheck;
use App\Task\AppUserNotice;
use App\Task\AsyncOss;
use App\Task\CheckAccount;
use App\Task\CheckIsDownloadApp;
use App\Task\CheckUdidToken;
use App\Task\RedisUdidList;
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
        $ip2 = $long ? [$ip, $long] : [substr($ip,0,250), 0];
        return $ip2[0];
    }

    protected function getTable($table, $user_id, $sn = 10)
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), $sn));
        return $table . "_" . $ext;
    }

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

    public function index()
    {
//        $file = EASYSWOOLE_ROOT . '/vendor/easyswoole/easyswoole/src/Resource/Http/welcome.html';
//        if (!is_file($file)) {
//            $file = EASYSWOOLE_ROOT . '/src/Resource/Http/404.html';
//        }
//        $this->response()->write(file_get_contents($file));
        $str="HELLO World!!";
        $this->response()->write($str);
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

    protected function download()
    {
        try {
            $methd = $this->request()->getMethod();
            $ip = $this->getIp();
            $tag = $this->request()->getRequestParam('tag');
            $udid = $this->request()->getRequestParam('udid');
            if (empty($tag) || empty($udid)) {
                $this->response()->withStatus(404);
                $this->response()->end();
                return "";
            }
            $is_flow_tag =  RedisPool::invoke(function (Redis $redis) use ($tag,$udid) {
                $redis->select(7);
                return $redis->get($tag.":".$udid);
            });
            if(empty($is_flow_tag)){
                $this->response()->withStatus(404);
                $this->response()->end();
                return "";
            }
            $app = RedisLib::get("app_tag:".trim($tag),4);
            if(empty($app)) {
                $app = DbManager::getInstance()->invoke(function ($client) use ($tag) {
                    $data = App::invoke($client)->where('tag', $tag)
                        ->where("is_delete", 1)
                        ->where("status", 1)
                        ->where("is_download", 0)
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return 1;
                    }
                });
                RedisLib::set("app_tag:".trim($tag),$app,4,600);
            }

            if (empty($app) || !is_array($app)) {
                /***应用不存在**/
                $this->response()->withStatus(404);
                $this->response()->end();
                return "";
            }
            /**下架**/
            if($app["is_stop"] == 1){
                $this->response()->withStatus(404);
                $this->response()->end();
                return "";
            }
            if($methd=="HEAD"||$methd=="OPTIONS"){
                $this->response()->withHeader("Content-Type","application/octet-stream;charset=UTF-8");
                $this->response()->withHeader("Content-Length",$app["filesize"]);
                $this->response()->withHeader("accept-ranges","bytes");
                $this->response()->withStatus(200);
                $this->response()->end();
                return "";
            }

            $user = RedisLib::get("user_userId:".trim($app["user_id"]),4);
            if(empty($user)) {
                $user = DbManager::getInstance()->invoke(function ($client) use ($app) {
                    $data = User::invoke($client)->where('id', $app["user_id"])
                        ->where("status", "normal")
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return 1;
                    }
                });
                RedisLib::set("user_userId:".trim($app["user_id"]),$user,4,600);
            }
            if (empty($user) || !is_array($user) || $user["sign_num"]<=0) {
                /***应用不存在**/
                $this->response()->withStatus(404);
                $this->response()->end();
                return "";
            }
                $tool = new Tool();
                $is_pay = $tool->is_pay($app,$udid,$ip);
                if(!$is_pay){
                    Logger::getInstance()->error("扣费失败==$tag==$udid==");
                    $this->response()->withStatus(404);
                    $this->response()->end();
                    return "";
                }else{
                    $is_bale_rate = $is_pay;
                }
            if ($is_bale_rate) {
                /**重签名**/
                if ($app["is_resign"] == 1) {
                    $key = $udid . "_" . $tag;
                    $for = 0;
                    $re_val = null;
                    $time1 = time();
                    while ($for < 180) {
                        $re_val = RedisPool::invoke(function (Redis $redis) use ($key) {
                            $redis->select(3);
                            return $redis->get($key);
                        });
                        if ($re_val) {
                            break;
                        } else {
                            $for += 2;
                            \co::sleep(2);
                        }
                    }
                    if ($re_val) {
                        $re_val = json_decode($re_val, true);
                        /***指定下载域名***/
                        if (strrpos($re_val["oss_path"], "https") !== false) {
                            $this->response()->redirect($re_val["oss_path"] . "?time=" . time(), 301);
                            $this->response()->end();
                            return "";
                        }
                        if ($re_val["is_overseas"] == 20) {
                            $oss_name = "g_oss";
                        } else {
                            $oss_name = "oss";
                        }
                        $oss_config = RedisLib::get("oss:".$oss_name,4);
                        if(empty($oss_config)) {
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
                            RedisLib::set("oss:".$oss_name,$oss_config,4,600);
                        }
                        $oss = new Oss($oss_config);
                        $url = $oss->signUrl($re_val["oss_path"]);
                        $this->response()->redirect($url, 301);
                        $this->response()->end();
                        return "";
                    } else {
                        Logger::getInstance()->error("未查询到重签记录==$tag==$udid=====".(time()-$time1));
                        $this->response()->withStatus(404);
                        $this->response()->end();
                        return "";
                    }
                } else {
                    /**OSS分流***/
                    if ($is_bale_rate["is_overseas"] == 10) {
                            $pid = $user["pid"] ;
                            $oss_id  = DbManager::getInstance()->invoke(function ($client) use ($pid) {
                                $data = ProxyDomain::invoke($client)->where('user_id', $pid)->get();
                                if (!empty($data)) {
                                    return $data["oss_id"];
                                } else {
                                    return 0;
                                }
                            });
                            if($oss_id!=0){
                                $oss_config = DbManager::getInstance()->invoke(function ($client) use ($oss_id) {
                                    $data = OssConfig::invoke($client)->where("id", $oss_id)
                                        ->where("status", 1)
                                        ->get();
                                    if (!empty($data)) {
                                        return $data->toArray();
                                    } else {
                                        return null;
                                    }
                                });
                                if(!empty($oss_config)){
                                    $oss = new Oss($oss_config);
                                    $url = $oss->signUrl($app["oss_path"]);
                                    $this->response()->redirect($url, 301);
                                    $this->response()->end();
                                    return "";
                                }
                            }else{
                                $oss_name = "oss";
                                $oss_config = RedisLib::get("oss:".$oss_name,4);
                                if(empty($oss_config)) {
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
                                    RedisLib::set("oss:".$oss_name,$oss_config,4,600);
                                }
                                if(!empty($oss_config)){
                                    $oss = new Oss($oss_config);
                                    $url = $oss->signUrl($app["oss_path"]);
                                    $this->response()->redirect($url, 301);
                                    $this->response()->end();
                                    return "";
                                }
                            }
                    }else{
                        /**使用google**/
                        $is_google = DbManager::getInstance()->invoke(function ($client) {
                            $data = SiteConfig::invoke($client)->where("name", "is_google")
                                ->get();
                            if (!empty($data)) {
                                return $data->toArray();
                            } else {
                                return null;
                            }
                        });
//                        $is_google = [
//                            "value"=>1
//                        ];
                        if(!empty($is_google) && $is_google["value"]==1){
                            /***谷歌私有库**/
                            $google_private_oss_config = Config::getInstance()->getConf("Google_PRIVATE_OSS");
                            $google_private = new GoogleOss($google_private_oss_config);
                            $url = $google_private->signUrl($app["oss_path"]);
                            $this->response()->redirect($url, 301);
                            $this->response()->end();
                            return "";
                        }else{
                            /**海外私有库**/
                            $oss_name = "g_ipa_oss";
                            $oss_config = RedisLib::get("oss:".$oss_name,4);
                            if(empty($oss_config)) {
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
                                RedisLib::set("oss:".$oss_name,$oss_config,4,600);
                            }
                            if(!empty($oss_config)){
                                $oss = new Oss($oss_config);
                                $url = $oss->signUrl($app["oss_path"]);
                                $this->response()->redirect($url, 301);
                                $this->response()->end();
                                return "";
                            }
                        }
                    }
                }
            } else {
                Logger::getInstance()->error("没有扣费信息==$tag==$udid==");
                $this->response()->withStatus(404);
                $this->response()->end();
                return "";
            }
        } catch (\Throwable $exception) {
            Logger::getInstance()->error("getMessage====" . $exception->getMessage());
            $this->response()->withStatus(404);
            $this->response()->end();
            return "";
        }
    }


    public function is_check()
    {
        $params = $this->request()->getRequestParam();
        if(empty($params)){
            $content = $this->request()->getBody()->__toString();
            $params = json_decode($content, true);
        }
        $AliyunTag = isset($params["AliyunTag"]) ? $params["AliyunTag"] : '';
        $device = isset($params["device"]) ? $params["device"] : '';
        $idfv = isset($params["idfv"]) ? $params["idfv"] : '';
        $version = isset($params["version"]) ? $params["version"] : '';
        $osversion = isset($params["osversion"]) ? $params["osversion"] : '';
        $sign = isset($params["sign"]) ? $params["sign"] : '';
        $ip = $this->getIp();
        if (empty($AliyunTag) || empty($device) || empty($osversion) || empty($sign) || empty($version)) {
            return $this->success("success", ["code" => 0], 200);
        }
        if ($version !== "2.0") {
            return $this->success('success', ["code" => 0], 200);
        }
        if ($sign !== strtoupper(md5($AliyunTag . $idfv . "QKSIGN"))) {
            return $this->success('success', ["code" => 0], 200);
        }
        $app = RedisLib::get("app_tag:".trim($AliyunTag),4);
        if(empty($app)) {
            $app = DbManager::getInstance()->invoke(function ($client) use ($AliyunTag) {
                $data = App::invoke($client)->where('tag', $AliyunTag)
                    ->where("is_delete", 1)
                    ->where("status", 1)
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return 1;
                }
            });
            RedisLib::set("app_tag:".trim($AliyunTag),$app,4,600);
        }
        if (empty($app)||!is_array($app)) {
            return $this->success('success', ["code" => 0], 200);
        }
        $user = RedisLib::get("user_userId:".trim($app["user_id"]),4);
        if(empty($user)) {
            $user = DbManager::getInstance()->invoke(function ($client) use ($app) {
                $data = User::invoke($client)->where('id', $app["user_id"])
                    ->where("status", "normal")
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return 1;
                }
            });
            RedisLib::set("user_userId:".trim($app["user_id"]),$user,4,600);
        }
        if (empty($user)||!is_array($user)) {
            return $this->success('success', ["code" => 0], 200);
        }
        $is_exit_install  = RedisLib::hGetAll("appInstall_idfv:".$app["id"].":".$idfv,5);
        if(empty($is_exit_install)){
            $is_exit_install = DbManager::getInstance()->invoke(function ($client) use ($app, $idfv) {
                $data = AppInstallCallback::invoke($client)->where('app_id', $app["id"])
                    ->where("idfv", $idfv)
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
            RedisLib::hMSet("appInstall_idfv:".$app["id"].":".$idfv,$is_exit_install,5);
        }
        if (empty($is_exit_install)) {
            if ($app["is_resign"] == 1) {
                return $this->success('success', ["code" => 0], 200);
            }
            $ip_num = RedisLib::get("ip_num:".$app["id"].":".$ip,7);
            if($ip_num<20){
                $ip_num = DbManager::getInstance()->invoke(function ($client) use ($app, $ip) {
                    return AppInstallCallback::invoke($client)->where('app_id', $app["id"])
                        ->where("ip", $ip)
                        ->count("id");
                });
                RedisLib::set("ip_num:".$app["id"].":".$ip,$ip_num,7,600);
            }
            if ($ip_num >= 20) {
                return $this->success('success', ["code" => 0], 200);
            }
            $install_data = [
                "app_id" => $app["id"],
                "user_id" => $app["user_id"],
                "idfv" => $idfv,
                "device" => $device,
                "osversion" => $osversion,
                "create_time" => date("Y-m-d H:i:s"),
                "ip" => $ip,
                "post_data" => json_encode($params)
            ];
            $install_data_id = DbManager::getInstance()->invoke(function ($client) use ($install_data) {
               return AppInstallCallback::invoke($client)->data($install_data, false)->save();
            });
            $install_data["id"]=$install_data_id;
            RedisLib::hMSet("appInstall_idfv:".$app["id"].":".$idfv,$install_data,5);
        }
        return $this->success('success', ["code" => 1], 200);
    }

    public function is_udid_check()
    {
        $params = $this->request()->getRequestParam();
        if(empty($params)){
            $content = $this->request()->getBody()->__toString();
            $params = json_decode($content, true);
        }
        $ip = $this->getIp();
        $AliyunTag = isset($params["AliyunTag"]) ? $params["AliyunTag"] : '';
        $device = isset($params["device"]) ? $params["device"] : '';
        $idfv = isset($params["idfv"]) ? $params["idfv"] : '';
        $udid = isset($params["udid"]) ? $params["udid"] : '';
        $version = isset($params["version"]) ? $params["version"] : '';
        $osversion = isset($params["osversion"]) ? $params["osversion"] : '';
        $sign = isset($params["sign"]) ? $params["sign"] : '';
        if (empty($AliyunTag) || empty($device) || empty($osversion) || empty($sign) || empty($version) || empty($udid) || empty($idfv)) {
            return $this->success('success', ["code" => 0], 200);
        }
        $new_sign = strtoupper(md5($AliyunTag . $idfv . "QK-SIGN" . $version . $udid));
        if ($new_sign !== $sign) {
            $this->addError("签名验证错误", $params, $AliyunTag, $ip);
            return $this->success('success', ["code" => 0], 200);
        }
        $app = RedisLib::get("app_tag:".trim($AliyunTag),4);
        if(empty($app)){
            $app = DbManager::getInstance()->invoke(function ($client) use ($AliyunTag) {
                $data = App::invoke($client)->where('tag', $AliyunTag)
                    ->where("is_delete", 1)
                    ->where("status", 1)
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return 1;
                }
            });
            RedisLib::set("app_tag:".trim($AliyunTag),$app,4,600);
        }
        if (empty($app)||!is_array($app)) {
            $this->addError("APP不存在", $params, $AliyunTag, $ip);
            return $this->success('success', ["code" => 0], 200);
        }
        $user = RedisLib::get("user_userId:".trim($app["user_id"]),4);
        if(empty($user)) {
            $user = DbManager::getInstance()->invoke(function ($client) use ($app) {
                $data = User::invoke($client)->where('id', $app["user_id"])
                    ->where("status", "normal")
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return 1;
                }
            });
            RedisLib::set("user_userId:".trim($app["user_id"]),$user,4,600);
        }
        if (empty($user)||!is_array($user)) {
            $this->addError("用户不存在", $params, $AliyunTag, $ip, $app["id"]);
            return $this->success('success', ["code" => 0], 200);
        }
        $bale_rate_table = $this->getTable("proxy_bale_rate", $user["pid"]);
        /**是否付费**/
        $is_pay = RedisLib::get("pay:".$app["id"].":".$udid,6);
        if(empty($is_pay)){
            $is_pay = DbManager::getInstance()->invoke(function ($client) use ($app, $udid, $bale_rate_table) {
                $data = BaleRate::invoke($client)->tableName($bale_rate_table)
                    ->where("app_id", $app["id"])
                    ->where("udid", $udid)
                    ->where("user_id", $app["user_id"])
                    ->where("status", 1)
                    ->get();
                if (!empty($data)) {
                    return 1;
                } else {
                    return 0;
                }
            });
            RedisLib::set("pay:".$app["id"].":".$udid,$is_pay,6,3600);
        }
        if ($is_pay==0) {
            $this->addError("无扣费记录", $params, $AliyunTag, $ip, $app["id"]);
            return $this->success('success', ["code" => 0], 200);
        }
        /**绑定UDID**/
        $is_exit_install = RedisLib::hGetAll("appInstall_udid:".$app["id"].":".$udid,5);
        if(empty($is_exit_install)){
            $is_exit_install = DbManager::getInstance()->invoke(function ($client) use ($app, $udid) {
                $data = AppInstallCallback::invoke($client)->where('app_id', $app["id"])
                    ->where("udid", $udid)
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
            RedisLib::hMSet("appInstall_udid:".$app["id"].":".$udid,$is_exit_install,5);
        }
        if (empty($is_exit_install)) {
            $is_exit_install  = RedisLib::hGetAll("appInstall_idfv:".$app["id"].":".$idfv,5);
            if(empty($is_exit_install)||!isset($is_exit_install["id"])){
                $is_exit_install = DbManager::getInstance()->invoke(function ($client) use ($app, $idfv) {
                    $data = AppInstallCallback::invoke($client)->where('app_id', $app["id"])
                        ->where("idfv", $idfv)
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
                RedisLib::hMSet("appInstall_idfv:".$app["id"].":".$idfv,$is_exit_install,5);
            }
            if ($is_exit_install) {
                $update = [
                    "udid" => $udid,
                    "post_data" => json_encode($params)
                ];
                DbManager::getInstance()->invoke(function ($client) use ($is_exit_install, $update) {
                    AppInstallCallback::invoke($client)->where('id', $is_exit_install["id"])->update($update);
                });
                RedisLib::hMSet("appInstall_udid:".$app["id"].":".$udid,array_merge($is_exit_install,$update),5);
                RedisLib::del("appInstall_idfv:".$app["id"].":".$idfv,5);
                RedisLib::hMSet("appInstall_idfv:".$app["id"].":".$idfv,array_merge($is_exit_install,$update),5);
                return $this->success('success', ["code" => 1], 200);
            } else {
                /**特殊闪退***/
                if ($app["is_download"] == 1) {
                    return $this->success('success', ["code" => 0], 200);
                }
                $install_data = [
                    "app_id" => $app["id"],
                    "user_id" => $app["user_id"],
                    "idfv" => $idfv,
                    "device" => $device,
                    "osversion" => $osversion,
                    "create_time" => date("Y-m-d H:i:s"),
                    "ip" => $ip,
                    "udid" => $udid,
                    "post_data" => json_encode($params)
                ];
                $install_data_id = DbManager::getInstance()->invoke(function ($client) use ($install_data) {
                   return AppInstallCallback::invoke($client)->data($install_data, false)->save();
                });
                $install_data["id"] = $install_data_id;
                RedisLib::hMSet("appInstall_udid:".$app["id"].":".$udid,$install_data,5);
                RedisLib::del("appInstall_idfv:".$app["id"].":".$idfv,5);
                RedisLib::hMSet("appInstall_idfv:".$app["id"].":".$idfv,$install_data,5);
                return $this->success('success', ["code" => 1], 200);
            }
        } else {
            if ($is_exit_install["idfv"] == $idfv) {
                return $this->success('success', ["code" => 1], 200);
            } else {
                $this->addError("UDID与IDFV不对应", $params, $AliyunTag, $ip, $app["id"]);
                return $this->success('success', ["code" => 0], 200);
            }
        }
    }

    /**
     * 错误记录
     * @param $error_info
     * @param $post_data
     * @param string $tag
     * @param string $ip
     * @param int $app_id
     * @return bool
     * @throws Exception
     * @throws PoolEmpty
     * @throws Throwable
     */
    protected function addError($error_info, $post_data, $tag = "", $ip = '', $app_id = 0)
    {
        $data = [
            "app_id" => $app_id,
            "error_info" => $error_info,
            "tag" => $tag,
            "post_data" => json_encode($post_data),
            "create_time" => date("Y-m-d H:i:s"),
            "ip" => $ip
        ];
        DbManager::getInstance()->invoke(function ($client) use ($data) {
            AppInstallError::invoke($client)->data($data, false)->save();
        });
        return true;
    }

    /**
     * 初始化接口
     * @return bool
     * @throws Exception
     * @throws PoolEmpty
     * @throws Throwable
     */
    public function init()
    {
        $method = $this->request()->getMethod();
        if($method=="OPTIONS"){
            $this->response()->withHeader('Access-Control-Allow-Origin', '*');
            $this->response()->withHeader('Access-Control-Allow-Headers', 'x-requested-with,content-type,token');
            $this->response()->withStatus(200);
            $this->response()->end();
            return true;
        }
        $domain = $this->request()->getRequestParam('domain');
        if (empty($domain)) {
            return $this->success('初始化失败', null, 0);
        }
        $proxy = DbManager::getInstance()->invoke(function ($client) use ($domain) {
            $data = ProxyDomain::invoke($client)->where('domain', $domain)
                ->field('id,domain,logo,logo_name,qq,skype,telegram,is_other_login')
                ->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
        if (empty($proxy)) {
            return $this->success('初始化失败', null, 0);
        }
        $proxy['qq'] = empty($proxy['qq']) ? null : explode(',', $proxy['qq']);
        $proxy['skype'] = empty($proxy['skype']) ? null : explode(',', $proxy['skype']);
        $proxy['telegram'] = empty($proxy['telegram']) ? null : explode(',', $proxy['telegram']);
        if (!empty($proxy['qq']) || !empty($proxy['skype']) || !empty($proxy['telegram'])) {
            $proxy['is_kefu'] = 1;
        } else {
            $proxy['is_kefu'] = 0;
        }
        if ($proxy["logo"]) {
            $ip = $this->getIp();
            $ip2 = new Ip2Region();
            $ip_address = $ip2->memorySearch($ip);
            if(!empty($ip_address)) {
                $address = explode('|', $ip_address['region']);
                if ($address[0] == "中国" && !in_array($address[2], ["澳门", '香港', "台湾省","台湾"])) {
                    $oss_name = "oss";
                } else {
                    $oss_name = "g_oss";
                }
            }else{
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
            $proxy["logo"] = $oss->signUrl($proxy["logo"]);
        }
        return $this->success('ok', $proxy, 200);
    }

    /**
     * 获取公告
     * @return bool
     * @throws Exception
     * @throws PoolEmpty
     * @throws Throwable
     */
    public function getBulletin()
    {
        $method = $this->request()->getMethod();
        if($method=="OPTIONS"){
            $this->response()->withHeader('Access-Control-Allow-Origin', '*');
            $this->response()->withHeader('Access-Control-Allow-Headers', 'x-requested-with,content-type,token');
            $this->response()->withStatus(200);
            $this->response()->end();
            return true;
        }
        $bulletin = DbManager::getInstance()->invoke(function ($client) {
            $data = SiteConfig::invoke($client)->where("name", "proxy_bulletin")
                ->get();
            if (!empty($data)) {
                return $data["value"];
            } else {
                return null;
            }
        });
        $crash = DbManager::getInstance()->invoke(function ($client) {
            $data = SiteConfig::invoke($client)->where("name", "proxy_crash")
                ->get();
            if (!empty($data)) {
                return $data["value"];
            } else {
                return null;
            }
        });
        $data = [
            'bulletin' => $bulletin ? $bulletin : null,
            'crash' => $crash ? $crash : null,
        ];
        return $this->success('ok', $data, 200);
    }

    public function style()
    {
        $method = $this->request()->getMethod();
        if($method=="OPTIONS"){
            $this->response()->withHeader('Access-Control-Allow-Origin', '*');
            $this->response()->withHeader('Access-Control-Allow-Headers', 'x-requested-with,content-type,token');
            $this->response()->withStatus(200);
            $this->response()->end();
            return true;
        }
        $domain = $this->request()->getRequestParam('domain');
        if (empty($domain)) {
            return $this->success('fail', null, 0);
        }
        $ip = $this->getIp();
        $ip2 = new Ip2Region();
        $ip_address = $ip2->memorySearch($ip);
        if(!empty($ip_address)) {
            $address = explode('|', $ip_address['region']);
            if ($address[0] == "中国" && !in_array($address[2], ["澳门", '香港', "台湾省","台湾"])) {
                $type = 10;
            } else {
                $type = 20;
            }
        }else{
            $type = 20;
        }
//        $style_id = RedisLib::get("domain:style_id:".trim($domain),5);
//        if(empty($style_id)){
            $style_id = DbManager::getInstance()->invoke(function ($client) use ($domain) {
                $data = ProxyDomain::invoke($client)->where('domain', $domain)
                    ->field('id,domain,style_id')
                    ->get();
                if (!empty($data)) {
                    return $data["style_id"];
                } else {
                    return null;
                }
            });
//            RedisLib::set("domain:style_id:".trim($domain),$style_id,5,600);
//        }
//        $style_name = RedisLib::get("domain:style_name:".trim($style_id),5);
//        if(empty($style_name)){
            $style_name = DbManager::getInstance()->invoke(function ($client) use ($style_id) {
                $data = ProxyStyle::invoke($client)->where('id', $style_id)
                    ->where('status', 1)
                    ->get();
                if (!empty($data)) {
                    return $data["name"];
                } else {
                    return null;
                }
            });
//            RedisLib::set("domain:style_name:".trim($style_id),$style_name,5,600);
//        }

        if (empty($style_name)) {
//            $style = RedisLib::get("domain:type:".trim($type),5);
//            if(empty($style)){
                $style = DbManager::getInstance()->invoke(function ($client) use ($type) {
                    $data = ProxyStyle::invoke($client)->where('type', $type)
                        ->where('status', 1)
                        ->where('is_default', 1)
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
//                RedisLib::set("domain:type:".trim($type),$style,5,600);
//            }
        } else {
//            $style = RedisLib::get("domain:type-".$type.":style:".trim($style_name),5);
//            if(empty($style)) {
                $style = DbManager::getInstance()->invoke(function ($client) use ($style_name, $type) {
                    $data = ProxyStyle::invoke($client)->where('type', $type)
                        ->where('status', 1)
                        ->where('name', trim($style_name))
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
//                RedisLib::set("domain:type-".$type.":style:".trim($style_name),$style,5,600);
//            }
            if (empty($style)) {
//                $style = RedisLib::get("domain:type:".trim($type),5);
//                if(empty($style)){
                    $style = DbManager::getInstance()->invoke(function ($client) use ($type) {
                        $data = ProxyStyle::invoke($client)->where('type', $type)
                            ->where('status', 1)
                            ->where('is_default', 1)
                            ->get();
                        if (!empty($data)) {
                            return $data->toArray();
                        } else {
                            return null;
                        }
                    });
//                    RedisLib::set("domain:type:".trim($type),$style,5,600);
//                }
            }
        }
        $js = explode(',', $style['js']);
        $css = explode(',', $style['css']);
        return $this->success('ok', ['js' => $js, 'css' => $css, "v" => $style_name], 200);
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
                "time"=>time()
            ];
            RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($key, $value) {
                $redis->select(3);
                $redis->set($key, json_encode($value), 600);
            });

            RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($tag,$udid) {
                $redis->select(9);
                $redis->set($tag.":".$udid,"重签任务完成", 600);
            });
            return $this->writeJson(200);
        }
    }
    public function xk_key_redis(){
        $app_id = $this->request()->getRequestParam("app_id");
        $update_time = $this->request()->getRequestParam("update_time");
        $oss_path = $this->request()->getRequestParam("oss_path");
        $tag = $this->request()->getRequestParam("tag");
        $xk_appid = $this->request()->getRequestParam("xk_appid");
        $type = $this->request()->getRequestParam("app_type");
       
        if(empty($app_id)||empty($oss_path)||empty($tag)){
            return $this->writeJson(500);
        }else{
            //增加签名信息到sql数据库
            $record=[
                "oss_path" => $oss_path,
                "ipa_update_time" => $update_time,
                "create_time"=>date('Y-m-d H:i:s'),
                "app_id" => $app_id,
            ];
            try{
                /*DbManager::getInstance()->invoke(function ($client) use ($record) {
                    V3signRecord::invoke($client)->data($record, false)->save();
                });*/
                DbManager::getInstance()->invoke(function ($client) use ($type,$oss_path,$xk_appid, $app_id) {
                    if($type == 1)
                    {
                        App::invoke($client)->where('id', $app_id)->update(['xk_appid'=>$xk_appid,'oss_path'=>$oss_path]);
                    }
                    else
                        App::invoke($client)->where('id', $app_id)->update(['xk_appid'=>$xk_appid]);
                });
            }catch(Throwable $e){
                Logger::getInstance()->error($e->getMessage());
                return $this->writeJson(500);
            }
            
            //保存签名记录信息到redis
            $key = $app_id . "_" . $tag;
            $value = [
                "oss_path" => $oss_path,
                "ipa_update_time" => $update_time
            ];
            RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($key, $value) {
                $redis->select(3);
                $redis->set($key, json_encode($value));
            });
            //更新redis签名任务状态为已完成
            RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($tag,$app_id) {
                $redis->select(9);
                $redis->set($tag.":".$app_id,"xk签名任务完成", 600);
                $redis->select(1);
                $redis->del("is_xk_task:" .$app_id);
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

    /***
     * 更新包初始化
     * @return bool
     * @throws Exception
     * @throws PoolEmpty
     * @throws Throwable
     */
    public function update_init_app(){
        $params = $this->request()->getRequestParam();
        if(empty($params["app_id"])||empty($params["tag"])||empty($params["sign"])){
            return $this->success('fail', null, 200);
        }
        if($params["sign"]!=strtoupper(md5($params["app_id"].$params["tag"]))){
            return $this->success('fail', null, 200);
        }
        $app = DbManager::getInstance()->invoke(function ($client) use ($params) {
            $data = App::invoke($client)->where('id', $params["app_id"])
                ->where("is_delete", 1)
                ->get();
            if (!empty($data)) {
                return $data->toArray();
            } else {
                return null;
            }
        });
        if(empty($app)){
            return  $this->success('fail', null, 200);
        }
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
        if(empty($user)){
            return  $this->success('fail', null, 200);
        }
        /**OSS分流***/
        $async_oss_config =  DbManager::getInstance()->invoke(function ($client)use ($user){
            $data = ProxyUserDomain::invoke($client)->where('user_id', $user["pid"])->get();
            if($data["oss_id"]){
                $async_oss_config = OssConfig::invoke($client)->where("id",$data["oss_id"])->get();
                if(!empty($async_oss_config)){
                    return  $async_oss_config->toArray();
                }
            }
            return null;
        });
        if($async_oss_config){
            $oss_id = $async_oss_config["id"];
        }else{
            $oss_id =0;
        }
        /**谷歌上传**/
        if(isset($params["type"])&&$params["type"]==20){
            $oss_config = Config::getInstance()->getConf("G_OSS");
            $oss = new Oss($oss_config);
            if($oss->is_exit($params["oss_path"])){
//                /**是否需要审核**/
//                if(isset($params["is_update"])&&$params["is_update"]==1){
//                    $update = [
//                        "is_update"=>1,
//                        "update_data"=>json_encode($params)
//                    ];
//                    $url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
//                    $post_data = [
//                        "chat_id" =>"-667020081",
//                        "text" => "APP:< ".$app["name"]." > ,APP_ID: < ".$app["id"]." > ,有更新，请及时审核。",
//                    ];
//                    $tool = new Tool();
//                    $result = $tool->http_client($url, $post_data);
//                }else{
//
//                }
                $update=[
                    "account_id" => $params["account_id"],
                    "oss_path" => $params["oss_path"],
                    'package_name' => $params['package_name'],
                    "update_time" => date("Y-m-d H:i:s")
                ];
                DbManager::getInstance()->invoke(function ($client) use ($params,$update) {
                    App::invoke($client)->where("id", $params["app_id"])
                        ->update($update);
                });
                /**清除缓存**/
                RedisLib::del("app_tag:".$app["tag"],0);
                RedisLib::del("app_tag:".$app["tag"],4);
                RedisLib::del("app_short_url:".$app["short_url"],4);
                RedisLib::del("sign_app_loading:".$app["id"],8);
            }else{
                $params["start_time"] = time();
                RedisPool::invoke(function (Redis $redis) use ($params) {
                    $redis->select(11);
                    $redis->sAdd("oss_to_google", json_encode($params));
                });
                /**
                 * @todo 谷歌同步
                 */
                $tool = new Tool();
                $post_google = [
                    "oss_path" => $params["oss_path"],
                    "sign" => strtoupper(md5($params["oss_path"] . "kiopmwhyusn")),
                    "oss_id"=>$oss_id,
                    "async_oss_config"=>$async_oss_config,
                ];
                $result = $tool->http_client("http://35.227.214.161/index/google_to_oss", $post_google);
//                $result = $tool->http_client("http://34.117.236.200/index/google_to_oss", $post_google);
            }
        }else {
            $google_private_oss_config = Config::getInstance()->getConf("Google_PRIVATE_OSS");
            $google_oss = new GoogleOss($google_private_oss_config);
            /**同步成功**/
            if ($google_oss->exists($params["oss_path"])) {
//                /**是否需要审核**/
//                if(isset($params["is_update"])&&$params["is_update"]==1){
//                    $update = [
//                        "is_update"=>1,
//                        "update_data"=>json_encode($params)
//                    ];
//                    $url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
//                    $post_data = [
//                        "chat_id" =>"-667020081",
//                        "text" => "APP:< ".$app["name"]." > ,APP_ID: < ".$app["id"]." > ,有更新，请及时审核。",
//                    ];
//                    $tool = new Tool();
//                    $result = $tool->http_client($url, $post_data);
//                }else{
//
//                }
                $update=[
                    "account_id" => $params["account_id"],
                    "oss_path" => $params["oss_path"],
                    'package_name' => $params['package_name'],
                    "update_time" => date("Y-m-d H:i:s")
                ];
                DbManager::getInstance()->invoke(function ($client) use ($params,$update) {
                    App::invoke($client)->where("id", $params["app_id"])
                        ->update($update);
                });
                /**清除缓存**/
                RedisLib::del("app_tag:".$app["tag"],0);
                RedisLib::del("app_tag:".$app["tag"],4);
                RedisLib::del("app_short_url:".$app["short_url"],4);
                RedisLib::del("sign_app_loading:".$app["id"],8);
            } else {
                $params["start_time"] = time();
                $params["type"] = 10;
                RedisPool::invoke(function (Redis $redis) use ($params) {
                    $redis->select(11);
                    $redis->sAdd("oss_to_google", json_encode($params));
                });
                /**
                 * @todo 谷歌同步
                 */
                $tool = new Tool();
                $post_google = [
                    "oss_path" => $params["oss_path"],
                    "sign" => strtoupper(md5($params["oss_path"] . "kiopmwhyusn")),
                    "oss_id"=>$oss_id,
                    "async_oss_config"=>$async_oss_config,
                ];
                $result = $tool->http_client("http://35.227.214.161/index/oss_to_google", $post_google);
//                $result = $tool->http_client("http://34.117.236.200/index/oss_to_google", $post_google);
            }
        }
        return $this->success('success', null, 200);
    }

//    public function close_resign(){
//        TaskManager::getInstance()->async(new AppDownloadCheck());
//        return $this->success('success', null, 200);
//    }

    public function checkISDownloadApp(){
        TaskManager::getInstance()->async(new CheckIsDownloadApp());
        return $this->success('success', null, 200);
    }

    public function day_notice(){
        TaskManager::getInstance()->async(new AppUserNotice());
        return $this->success('day_notice', null, 200);
    }

    public function check_account(){
        TaskManager::getInstance()->async(new CheckAccount());
        return $this->success('check_account', null, 200);
    }

    protected function udid_list(){
        TaskManager::getInstance()->async(new RedisUdidList());
        return $this->success('udid_list', null, 200);
    }
    



}