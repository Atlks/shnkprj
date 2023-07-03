<?php


namespace App\HttpController;


use App\Lib\GoogleOss;
use App\Lib\Oss;
use App\Lib\RedisLib;
use App\Lib\Tool;
use App\Mode\App;
use App\Mode\OssConfig;
use App\Mode\ProxyDomain;
use App\Mode\SiteConfig;
use App\Mode\User;
use co;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\ORM\DbManager;
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
        $ip2 = $long ? [$ip, $long] : [substr($ip, 0, 250), 0];
        return $ip2[0];
    }

    public function index()
    {
        $file = EASYSWOOLE_ROOT.'/extend/404.html';
//        $file = EASYSWOOLE_ROOT.'/vendor/easyswoole/easyswoole/src/Resource/Http/welcome.html';
//        if(!is_file($file)){
//            $file = EASYSWOOLE_ROOT.'/src/Resource/Http/welcome.html';
//        }
//        $str = "HELLO World!!";
        $this->response()->write(file_get_contents($file));
    }

//    function test()
//    {
//        $this->response()->write('this is test');
//    }

    protected function actionNotFound(?string $action)
    {
        $this->response()->withStatus(404);
        $file = EASYSWOOLE_ROOT . '/vendor/easyswoole/easyswoole/src/Resource/Http/404.html';
        if (!is_file($file)) {
            $file = EASYSWOOLE_ROOT . '/src/Resource/Http/404.html';
        }
        $this->response()->write(file_get_contents($file));
    }

    public function download()
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
            $is_flow_tag = RedisPool::invoke(function (Redis $redis) use ($tag, $udid) {
                $redis->select(7);
                return $redis->get($tag . ":" . $udid);
            });
            if (empty($is_flow_tag)) {
                $this->response()->withStatus(404);
                $this->response()->end();
                return "";
            }
            $app = RedisLib::get("app_tag:" . trim($tag), 4);
            if (empty($app)) {
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
                RedisLib::set("app_tag:" . trim($tag), $app, 4, 600);
            }

            if (empty($app) || !is_array($app)) {
                /***应用不存在**/
                $this->response()->withStatus(404);
                $this->response()->end();
                return "";
            }
            /**下架**/
            if ($app["is_stop"] == 1) {
                $this->response()->withStatus(404);
                $this->response()->end();
                return "";
            }
            if ($methd == "HEAD" || $methd == "OPTIONS") {
                $this->response()->withHeader("Content-Type", "application/octet-stream;charset=UTF-8");
                $this->response()->withHeader("Content-Length", $app["filesize"]);
                $this->response()->withHeader("accept-ranges", "bytes");
                $this->response()->withStatus(200);
                $this->response()->end();
                return "";
            }

            $user = RedisLib::get("user_userId:" . trim($app["user_id"]), 4);
            if (empty($user)) {
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
                RedisLib::set("user_userId:" . trim($app["user_id"]), $user, 4, 600);
            }
            if (empty($user) || !is_array($user) || $user["sign_num"] <= 0) {
                /***应用不存在**/
                $this->response()->withStatus(404);
                $this->response()->end();
                return "";
            }
            $tool = new Tool();
            $is_pay = $tool->is_pay($app, $udid, $ip);
            if (!$is_pay) {
                Logger::getInstance()->error("扣费失败==$tag==$udid==");
                $this->response()->withStatus(404);
                $this->response()->end();
                return "";
            } else {
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
                            co::sleep(2);
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
                        $oss_config = RedisLib::get("oss:" . $oss_name, 4);
                        if (empty($oss_config)) {
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
                            RedisLib::set("oss:" . $oss_name, $oss_config, 4, 600);
                        }
                        $oss = new Oss($oss_config);
                        $url = $oss->signUrl($re_val["oss_path"]);
                        $this->response()->redirect($url, 301);
                        $this->response()->end();
                        return "";
                    } else {
                        Logger::getInstance()->error("未查询到重签记录==$tag==$udid=====" . (time() - $time1));
                        $this->response()->withStatus(404);
                        $this->response()->end();
                        return "";
                    }
                } else {
                    /**OSS分流***/
                    if ($is_bale_rate["is_overseas"] == 10) {
                        $pid = $user["pid"];
                        $oss_id = DbManager::getInstance()->invoke(function ($client) use ($pid) {
                            $data = ProxyDomain::invoke($client)->where('user_id', $pid)->get();
                            if (!empty($data)) {
                                return $data["oss_id"];
                            } else {
                                return 0;
                            }
                        });
                        if ($oss_id != 0) {
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
                            if (!empty($oss_config)) {
                                $oss = new Oss($oss_config);
                                $url = $oss->signUrl($app["oss_path"]);
                                $this->response()->redirect($url, 301);
                                $this->response()->end();
                                return "";
                            }
                        } else {
                            $oss_name = "oss";
                            $oss_config = RedisLib::get("oss:" . $oss_name, 4);
                            if (empty($oss_config)) {
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
                                RedisLib::set("oss:" . $oss_name, $oss_config, 4, 600);
                            }
                            if (!empty($oss_config)) {
                                $oss = new Oss($oss_config);
                                $url = $oss->signUrl($app["oss_path"]);
                                $this->response()->redirect($url, 301);
                                $this->response()->end();
                                return "";
                            }
                        }
                    } else {
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
                        if (!empty($is_google) && $is_google["value"] == 1) {
                            /***谷歌私有库**/
                            $google_private_oss_config = Config::getInstance()->getConf("Google_PRIVATE_OSS");
                            $google_private = new GoogleOss($google_private_oss_config);
                            $url = $google_private->signUrl($app["oss_path"]);
                            $this->response()->redirect($url, 301);
                            $this->response()->end();
                            return "";
                        } else {
                            /**海外私有库**/
                            $oss_name = "g_ipa_oss";
                            $oss_config = RedisLib::get("oss:" . $oss_name, 4);
                            if (empty($oss_config)) {
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
                                RedisLib::set("oss:" . $oss_name, $oss_config, 4, 600);
                            }
                            if (!empty($oss_config)) {
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
        } catch (Throwable $exception) {
            Logger::getInstance()->error("getMessage====" . $exception->getMessage());
            $this->response()->withStatus(404);
            $this->response()->end();
            return "";
        }
    }
}