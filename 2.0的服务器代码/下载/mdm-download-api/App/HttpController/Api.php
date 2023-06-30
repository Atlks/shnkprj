<?php


namespace App\HttpController;


use App\Lib\Push;
use App\Lib\RedisLib;
use App\Model\BaleRate;
use App\Model\ProxyDownloadCodeList;
use App\Model\ProxyUser;
use App\Model\App;
use App\Model\ProxyAppViews;
use App\Model\SiteConfig;
use App\Model\UdidToken;
use App\Task\CheckInstallApp;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\ORM\DbManager;
use EasySwoole\ORM\Exception\Exception;
use EasySwoole\Oss\AliYun\OssClient;
use EasySwoole\Pool\Exception\PoolEmpty;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use Throwable;

class Api extends Controller
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
    public function get_kksign()
    {
        $host = $this->request->param("host");
        $uuid = $this->request->param('uuid');
        if (empty($uuid)) {
            $this->error('fail');
        }
        $uuid = get_short_url($uuid);
        $info = ProxyApp::where("short_url", $uuid)
            ->where("is_delete", 1)
            ->where("is_download", 0)
            ->cache(true, 300)
            ->find();
        if (empty($info)) {
            $this->error('fail');
        }
        
            $url_config = "safe_re_url_2";
            $re_url = Config::where("name", $url_config)
                ->value("value");
              
                $data = md5($uuid."2.0"."kksign");
                $this->success("success",["code" => 100], $data);
              }else{
                $this->error('fail');
              }
        
        

       
    }

    protected function getTable($table, $user_id, $sn = 10)
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), $sn));
        return $table . "_" . $ext;
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

    protected function getLanguage($name, $lang = "zh")
    {
        $lang_package = include ROOT_PATH . "/App/Lang/download.php";
        if (isset($lang_package[$lang][$name]) && !empty($lang_package[$lang][$name])) {
            return $lang_package[$lang][$name];
        } else {
            return $name;
        }
    }

    protected function actionNotFound(?string $action)
    {
        $this->response()->withStatus(404);
//        $file = EASYSWOOLE_ROOT.'/vendor/easyswoole/easyswoole/src/Resource/Http/404.html';
//        if(!is_file($file)){
//            $file = EASYSWOOLE_ROOT.'/src/Resource/Http/404.html';
//        }
        $file = EASYSWOOLE_ROOT . '/App/View/404.html';
        $this->response()->write(file_get_contents($file));
    }


    /**
     * 进度查询
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws Exception
     * @throws Throwable
     */
    public function progress()
    {
        $post = $this->request()->getRequestParam();
        $udid = $post["udid"] ?? "";
        $token = $post["token"] ?? "";
        $uuid = $post["uuid"] ?? "";
        $appenddata = $post["appenddata"] ?? "";
        if (empty($uuid)) {
            return $this->success(["code" => 404, "data" => []]);
        }
        if (empty($udid)) {
            if (empty($token)) {
                return $this->success(["code" => 0, "data" => []]);
            }
            /**UDID为空查询是否已经获取到UDID***/
            $is_exit = DbManager::getInstance()->invoke(function ($client) use ($token) {
                $data = UdidToken::invoke($client)
                    ->where("app_token", $token)
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
            if ($is_exit) {
                return $this->success(["code" => 1, "data" => ["udid" => $is_exit["udid"]]]);
            } else {
                return $this->success(["code" => 0, "data" => []]);
            }
        } else {
            if (strpos($uuid, ".html") !== false || strrpos($uuid, "/") !== false) {
                if (strrpos($uuid, "/") === false) {
                    $uuid = substr($uuid, 0, (strlen($uuid) - strpos($uuid, ".html")));
                } else {
                    $uuid = substr($uuid, 1, (strlen($uuid) - strpos($uuid, ".html")));
                }
            }
            if (strpos($uuid, ".") !== false) {
                $uuid = substr($uuid, 0, strpos($uuid, "."));
            }
            /***UDID可用**/
            $info = RedisLib::get("app_short_url:" . trim($uuid), 4);
            if (empty($info)) {
                $info = DbManager::getInstance()->invoke(function ($client) use ($uuid) {
                    $data = App::invoke($client)->where("short_url", $uuid)
                        ->where("is_delete", 1)
                        ->where("is_download", 0)
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
                RedisLib::set("app_short_url:" . trim($uuid), $info, 4, 600);
            }
            if (empty($info)) {
                return $this->success(["code" => 404, "data" => []]);
            }
            $is_exit = RedisLib::hGetAll("udidToken:" . $udid, 2);
            if (empty($is_exit)) {
                $is_exit = DbManager::getInstance()->invoke(function ($client) use ($udid) {
                    $data = UdidToken::invoke($client)->where("udid", $udid)->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
                if(!empty($is_exit)){
                    RedisLib::hMSet("udidToken:" . $udid, $is_exit, 2);
                }
            }
            /***还未安装完成**/
            if (empty($is_exit)||empty($is_exit["udid_token"])) {
                return $this->success(["code" => 0, "data" => []]);
            }
            $start = RedisLib::get("start_udid:" . $udid, 1);
            if (empty($start)) {
                /**记录初始时间**/
                RedisLib::del("start_udid:" . $udid, 1);
                RedisLib::set("start_udid:" . $udid, time(), 1, 600);
            }
            /***以删除**/
            if ($is_exit["is_delete"] != 1) {
                return $this->success(["code" => 500, "token" => $is_exit["app_token"]]);
            }
            /***中断后续操作**/
            $is_exit_check_app = RedisPool::invoke(function (Redis $redis) use ($udid) {
                $redis->select(1);
                return $redis->get("is_exit_check_app:" . $udid);
            });
            if ($is_exit_check_app) {
                return $this->success([
                    "code" => 3,
                    "token" => $is_exit["app_token"],
                    "msg" => $this->getLanguage('is_exit_app', $info["lang"]),
                    "btn_msg" => $this->getLanguage('is_exit_app_btn', $info["lang"]),
                ]);
            }

            /***任务投递***/
            $is_task = RedisPool::invoke(function (Redis $redis) use ($udid) {
                $redis->select(1);
                return $redis->get("is_task:" . $udid);
            });
            if (empty($is_task)) {
                RedisPool::invoke(function (Redis $redis) use ($udid) {
                    $redis->select(1);
                    return $redis->set("is_task:" . $udid, 1, 300);
                });
                /***下载码****/
                $is_code = RedisLib::get("is_code:" . $info["id"], 4);
                if (empty($is_code) || $is_code == 2) {
                    $is_code = DbManager::getInstance()->invoke(function ($client) use ($info) {
                        $data = ProxyDownloadCodeList::invoke($client)
                            ->where("app_id", $info["id"])
                            ->where("status", 1)
                            ->get();
                        if (!empty($data)) {
                            return true;
                        } else {
                            return false;
                        }
                    });
                    if ($is_code) {
                        /**有验证码**/
                        RedisLib::set("is_code:" . $info["id"], 2, 4, 300);
                        $is_use_code = DbManager::getInstance()->invoke(function ($client) use ($info, $udid) {
                            $is_use_code = ProxyDownloadCodeList::invoke($client)
                                ->where("app_id", $info["id"])
                                ->where("status", 1)
                                ->where("udid", $udid)
                                ->get();
                            if (!empty($is_use_code)) {
                                return false;
                            } else {
                                return true;
                            }
                        });
                        if ($is_use_code) {
                            return $this->success([
                                "code" => 2,
                                "token" => $is_exit["app_token"],
                                "msg" => "need code"
                            ]);
                        }
                    } else {
                        /**无验证码**/
                        RedisLib::set("is_code:" . $info["id"], 1, 4, 300);
                    }
                }

                /***token 有效**/
                $task_data = [
                    "udid" => $udid,
                    "app_token" => $token,
                    "app_id" => $info["id"]
                ];
                RedisPool::invoke(function (Redis $redis) use ($udid, $task_data) {
                    $redis->select(1);
                    return $redis->set("task:" . $udid, json_encode($task_data), 300);
                });
                /**注入参数**/
                if (!empty($appenddata)) {
                    if ($info["is_append"] == 1) {
                        if ($info["is_resign"] != 1) {
                            DbManager::getInstance()->invoke(function ($client) use ($info) {
                                App::invoke($client)->where('id', $info["id"])->update(["is_resign" => 1]);
                            });
                        }
                        $extend_data = json_decode(htmlspecialchars_decode(urldecode($appenddata)), true);
                        $extend_key = "append_data:" . $udid . "_" . $info["id"];
                        RedisPool::invoke(function (Redis $redis) use ($extend_key, $extend_data) {
                            $redis->select(1);
                            return $redis->set($extend_key, json_encode($extend_data), 300);
                        });
                    }
                }
//                if($udid =="be19f0577470e0ff5f7819908be04282a093b8af"){
                RedisPool::invoke(function (Redis $redis) use ($is_exit) {
                    $redis->select(6);
                    return $redis->rPush("download_push", json_encode([
                        "udid" => $is_exit["udid"],
                        "topic" => $is_exit["topic"],
                        "udid_token" => $is_exit["udid_token"],
                        "push_magic" => $is_exit["push_magic"],
                    ]));
                });
//                }else {
//                    /**老版证书***/
//                    if ($is_exit["topic"] == "com.apple.mgmt.External.117afaf2-ca3d-44d6-b8ff-4c1c30d40458") {
//                        $PEM = EASYSWOOLE_ROOT . "/other/push/m1/m1.pem";
//                    } elseif ($is_exit["topic"] == "com.apple.mgmt.External.88a08591-083a-4c02-b3a2-78aef5b67371") {
//                        $PEM = EASYSWOOLE_ROOT . "/other/push/m3/push.pem";
//                    } elseif ($is_exit['topic'] == "com.apple.mgmt.External.f8c97b0a-9368-41bf-a174-bd15c1a8adf0") {
//                        $PEM = EASYSWOOLE_ROOT . "/other/push/m4/push.pem";
//                    } else {
//                        $PEM = EASYSWOOLE_ROOT . "/other/push/m2/push.pem";
//                    }
//                    $push = new Push($PEM);
//                    $push->startMdm($is_exit["udid_token"], $is_exit["push_magic"]);
//                }
                return $this->success([
                    "code" => 100,
                    "token" => $is_exit["app_token"],
                    "msg" => "success"
                ]);
            } else {
                /***token 有效**/
                $task_data = [
                    "udid" => $udid,
                    "app_token" => $token,
                    "app_id" => $info["id"]
                ];
                RedisPool::invoke(function (Redis $redis) use ($udid, $task_data) {
                    $redis->select(1);
                    return $redis->set("task:" . $udid, json_encode($task_data), 300);
                });
                /**注入参数**/
                if (!empty($appenddata)) {
                    if ($info["is_append"] == 1) {
                        if ($info["is_resign"] != 1) {
                            DbManager::getInstance()->invoke(function ($client) use ($info) {
                                App::invoke($client)->where('id', $info["id"])->update(["is_resign" => 1]);
                            });
                        }
                        $extend_data = json_decode(htmlspecialchars_decode(urldecode($appenddata)), true);
                        $extend_key = "append_data:" . $udid . "_" . $info["id"];
                        RedisPool::invoke(function (Redis $redis) use ($extend_key, $extend_data) {
                            $redis->select(1);
                            return $redis->set($extend_key, json_encode($extend_data), 300);
                        });
                    }
                }
                RedisPool::invoke(function (Redis $redis) use ($is_exit) {
                    $redis->select(6);
                    return $redis->rPush("download_push", json_encode([
                        "udid" => $is_exit["udid"],
                        "topic" => $is_exit["topic"],
                        "udid_token" => $is_exit["udid_token"],
                        "push_magic" => $is_exit["push_magic"],
                    ]));
                });
                /***已推送完成***/
                if ($is_task == 2) {
                    return $this->success([
                        "code" => 200,
                        "token" => $is_exit["app_token"],
                        "msg" => "success"
                    ]);
                } else {
                    return $this->success([
                        "code" => 100,
                        "token" => $is_exit["app_token"],
                        "msg" => "success"
                    ]);
                }
            }
        }
    }

    /**
     * 检测安装
     * @return bool
     * @throws Exception
     * @throws PoolEmpty
     * @throws Throwable
     */
    public function is_install()
    {
        $post = $this->request()->getRequestParam();
        $udid = $post["udid"] ?? "";
        $short_url = $post["uuid"] ?? "";
        if (empty($short_url) || empty($udid)) {
            return $this->success(["code" => 100]);
        }
        $app = RedisLib::get("app_short_url:" . trim($short_url), 4);
        if (empty($app)) {
            $app = DbManager::getInstance()->invoke(function ($client) use ($short_url) {
                $data = App::invoke($client)->where("short_url", $short_url)
                    ->where("is_delete", 1)
                    ->where("is_download", 0)
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
            RedisLib::set("app_short_url:" . trim($short_url), $app, 4, 600);
        }
        if ($app) {
            /**检测安装状态***/
            RedisPool::invoke(function (Redis $redis) use ($udid) {
                $redis->select(1);
                $redis->del("is_task:" . $udid);
            });
            $user = RedisLib::get("user_userId:" . trim($app["user_id"]), 4);
            if (empty($user)) {
                $user = DbManager::getInstance()->invoke(function ($client) use ($app) {
                    $data = ProxyUser::invoke($client)
                        ->where("id", $app["user_id"])
                        ->where("status", "normal")
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
                RedisLib::set("user_userId:" . trim($app["user_id"]), $user, 4, 600);
            }
            $bale_rate_table = $this->getTable("proxy_bale_rate", $user["pid"]);
            $is_exit = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table, $app, $udid) {
                $data = BaleRate::invoke($client)->tableName($bale_rate_table)
                    ->where("app_id", $app["id"])
                    ->where("user_id", $app["user_id"])
                    ->where("udid", $udid)
                    ->where("status", 1)
                    ->where("is_install", 1)
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
            if ($is_exit) {
                /***已安装**/
                RedisPool::invoke(function (Redis $redis) use ($udid) {
                    $redis->select(1);
                    $redis->del("is_install:" . $udid);
                });
                return $this->success(["code" => 200]);
            } else {
                /**检测安装**/
                RedisPool::invoke(function (Redis $redis) use ($udid, $app) {
                    $redis->select(1);
                    $redis->set("is_install:" . $udid, $app["id"], 600);
                });
                $udid_token = RedisLib::hGetAll("udidToken:" . $udid, 2);
                if (empty($udid_token)) {
                    $udid_token = DbManager::getInstance()->invoke(function ($client) use ($udid) {
                        $data = UdidToken::invoke($client)->where("udid", $udid)->get();
                        if (!empty($data)) {
                            return $data->toArray();
                        } else {
                            return null;
                        }
                    });
                    RedisLib::hMSet("udidToken:" . $udid, $udid_token, 2);
                }
//                if($udid =="be19f0577470e0ff5f7819908be04282a093b8af"){
                RedisPool::invoke(function (Redis $redis) use ($udid_token) {
                    $redis->select(6);
                    return $redis->rPush("download_push", json_encode([
                        "udid" => $udid_token["udid"],
                        "topic" => $udid_token["topic"],
                        "udid_token" => $udid_token["udid_token"],
                        "push_magic" => $udid_token["push_magic"],
                    ]));
                });
//                }else {
//                    /**老版证书***/
//                    if ($udid_token["topic"] == "com.apple.mgmt.External.117afaf2-ca3d-44d6-b8ff-4c1c30d40458") {
//                        $PEM = EASYSWOOLE_ROOT . "/other/push/m1/m1.pem";
//                    } elseif ($udid_token["topic"] == "com.apple.mgmt.External.88a08591-083a-4c02-b3a2-78aef5b67371") {
//                        $PEM = EASYSWOOLE_ROOT . "/other/push/m3/push.pem";
//                    } elseif ($udid_token['topic'] == "com.apple.mgmt.External.f8c97b0a-9368-41bf-a174-bd15c1a8adf0") {
//                        $PEM = EASYSWOOLE_ROOT . "/other/push/m4/push.pem";
//                    } else {
//                        $PEM = EASYSWOOLE_ROOT . "/other/push/m2/push.pem";
//                    }
//                    $push = new Push($PEM);
//                    $push->startMdm($udid_token["udid_token"], $udid_token["push_magic"]);
//                }
            }
        }
        return $this->success(["code" => 100]);
    }

    /**
     * 访问记录
     * @return bool
     * @throws Exception
     * @throws PoolEmpty
     * @throws Throwable
     */
    public function urlViews()
    {
        $method = $this->request()->getMethod();
        $params = $this->request()->getRequestParam();
        if ($params && $method == "POST") {
            $ip = $this->getIp();
            $uuid = $params["uuid"] ?? '';
            $useragent = $params["useragent"] ?? '';
            $version = $params["version"] ?? '';
            $device = $params["device"] ?? '';
            $path = $params["path"] ?? '';
            $udid = $params["udid"] ?? '';
            $referer = $params["referer"] ?? '';
            if (empty($uuid)) {
                return $this->success(["data" => null, "code" => 1, "msg" => "success"]);
            }
            $app = RedisLib::get("app_short_url:" . trim($uuid), 4);
            if (empty($app)) {
                $app = DbManager::getInstance()->invoke(function ($client) use ($uuid) {
                    $data = App::invoke($client)->where("short_url", $uuid)
                        ->where("is_delete", 1)
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
                RedisLib::set("app_short_url:" . trim($uuid), $app, 4, 600);
            }
            if (empty($app)) {
                return $this->success(["data" => null, "code" => 1, "msg" => "success"]);
            }
            $user = RedisLib::get("user_userId:" . trim($app["user_id"]), 4);
            if (empty($user)) {
                $user = DbManager::getInstance()->invoke(function ($client) use ($app) {
                    $data = ProxyUser::invoke($client)->where("id", $app["user_id"])
                        ->where("status", "normal")
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
                RedisLib::set("user_userId:" . trim($app["user_id"]), $user, 4, 600);
            }
            $data = [
                'app_id' => $app['id'],
                'user_id' => $app['user_id'],
                'type' => 1,
                'ip' => $ip,
                'create_time' => date('Y-m-d H:i:s'),
                'useragent' => substr($useragent, 0, 200),
                'version' => $version,
                'url' => substr($path, 0, 200),
                'device' => $device,
                'udid' => $udid,
                'referer' => substr($referer, 0, 200),
            ];
            $view_table = $this->getTable("proxy_app_views", $user["pid"], 100);
            DbManager::getInstance()->invoke(function ($client) use ($view_table, $data) {
                ProxyAppViews::invoke($client)->tableName($view_table)->data($data, false)->save();
            });
        }
        return $this->success(["data" => null, "code" => 1, "msg" => "success"]);
    }

    /**
     * 获取原始数据
     * @return bool
     * @throws Exception
     * @throws PoolEmpty
     * @throws Throwable
     */
    public function get_origin_data()
    {
        $uuid = $this->request()->getRequestParam("uuid");
        if ($uuid) {
            $yum_uuid = $uuid;
            if (strpos($uuid, ".html") !== false || strrpos($uuid, "/") !== false) {
                if (strrpos($uuid, "/") === false) {
                    $uuid = substr($uuid, 0, (strlen($uuid) - strpos($uuid, ".html")));
                } else {
                    $uuid = substr($uuid, 1, (strlen($uuid) - strpos($uuid, ".html")));
                }
            }
            if (strpos($uuid, ".")) {
                $uuid = substr($uuid, 0, strpos($uuid, "."));
//                $uuid = str_replace(".","",$uuid);
            }
            $info = RedisLib::get("app_short_url:" . trim($uuid), 4);
            if (empty($info)) {
                $info = DbManager::getInstance()->invoke(function ($client) use ($uuid) {
                    $data = App::invoke($client)->where("short_url", $uuid)
                        ->where("is_delete", 1)
                        ->where("is_download", 0)
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
                RedisLib::set("app_short_url:" . trim($uuid), $info, 4, 600);
            }
            if (empty($info)) {
                //Logger::getInstance()->error("get_origin_data未查询到数据====$yum_uuid==$uuid=");
                return $this->success(['code' => 404, 'data' => '/404.html', 'msg' => 'fail']);
            }
            $lang = $info["lang"] ?? "zh";
            if ($info['status'] !== 1 || $info["is_stop"] == 1) {
                $status = 0;
            } else {
                $status = 1;
            }
            /***下载码****/
            $is_code = RedisLib::get("is_code:" . $info["id"], 4);
            if (empty($is_code)) {
                $is_code = DbManager::getInstance()->invoke(function ($client) use ($info) {
                    $data = ProxyDownloadCodeList::invoke($client)
                        ->where("app_id", $info["id"])
                        ->where("status", 1)
                        ->get();
                    if (!empty($data)) {
                        return 2;
                    } else {
                        return 1;
                    }
                });
                RedisLib::set("is_code:" . $info["id"], $is_code, 4, 300);
            }

            if (!empty($info["download_bg"])) {
                $public_url = DbManager::getInstance()->invoke(function ($client) {
                    $data = SiteConfig::invoke($client)->where("name", "proxy_zh_oss_public_url")
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
                $download_bg = $public_url["value"] . "/" . substr($info["download_bg"], strpos($info["download_bg"], 'upload/'));
            } else {
//                $public_url = DbManager::getInstance()->invoke(function ($client) {
//                    $data = SiteConfig::invoke($client)->where("name", "download_static_url")
//                        ->get();
//                    if (!empty($data)) {
//                        return $data->toArray();
//                    } else {
//                        return null;
//                    }
//                });
                $download_bg =  "/static/picture/bg.png";
            }
            $info["is_code"] = $is_code == 1 ? 0 : 1;
            $data = [
                "is_vaptcha" => $info["is_vaptcha"],
                "is_code" => $info["is_code"],
                "is_tip" => $info["is_tip"],
                "lang" => $lang,
                "copy_success" => $this->getLanguage('copy_success', $lang),
                "downloading" => $this->getLanguage('downloading', $lang),
                "Authorizing" => $this->getLanguage('Authorizing', $lang),
                "installing" => $this->getLanguage('installing', $lang),
                "preparing" => $this->getLanguage('preparing', $lang),
                "desktop" => $this->getLanguage('desktop', $lang),
                "install_config" => $this->getLanguage('install_config', $lang),
                "uuid" => $uuid,
                "status" => $status,
                "error_msg" => $this->getLanguage("appDismount", $lang),
                "apk_bg" => $download_bg
            ];
            return $this->success(['code' => 1, 'data' => $data, 'msg' => 'success']);
        } else {
            return $this->success(['code' => 0, 'data' => '', 'msg' => 'fail']);
        }
    }

    protected function onException(Throwable $throwable): void
    {
        Logger::getInstance()->error($throwable->getMessage());
        Logger::getInstance()->error($throwable->getLine());
        $this->success(['code' => 0, 'data' => '', 'msg' => 'fail']);
//        parent::onException($throwable); // TODO: Change the autogenerated stub
    }

    protected function check_install_app()
    {
        TaskManager::getInstance()->async(new CheckInstallApp());
        return $this->success(['code' => 200, 'data' => '', 'msg' => 'success']);
    }

}