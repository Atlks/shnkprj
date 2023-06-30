<?php

namespace app\controller;

use app\BaseController;
use app\lib\GoogleOss;
use app\lib\Ios;
use app\lib\Ip2Region;
use app\lib\Oss;
use app\lib\Pay;
use app\lib\Redis;
use app\lib\Push;
use app\lib\WyDun;
use app\model\Account;
use app\model\App;
use app\model\AppCachePush;
use app\model\Config;
use app\model\DomainList;
use app\model\DownloadPayApp;
use app\model\DownloadUrl;
use app\model\OssConfig;
use app\model\ProxyApp;
use app\model\ProxyAppApkDownloadLog;
use app\model\ProxyDownloadCodeList;
use app\model\ProxyUser;
use app\model\ProxyUserDomain;
use app\model\ProxyUserDomainHistory;
use app\model\UdidToken;
use app\model\User;
use CFPropertyList\CFPropertyList;
use Exception;
use fast\Random;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\db\Where;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\response\Json;
use think\Validate;

class Index extends BaseController
{

    public function index()
    {
        $ip = $this->ip();
        $host = $this->request->host();
        $uuid = $this->request->param('uuid');
        $udid = $this->request->param('udid', "");
        $kk = $this->request->param('kk', "");
        $referer = $this->request->server("HTTP_REFERER");
        if (empty($uuid)) {
            if (!empty($kk)) {
                Log::write("==$kk==短链参数不存在==" . json_encode($_SERVER) . "=====$ip=\r\n", "info");
            }
            return redirect("/404.html");
        }
        if (!empty($kk)) {
            Log::write("===$kk===" . json_encode($_SERVER) . "========$ip=\r\n", "info");
        }
        $info = Redis::cacheGetArray("app_short_url:" . trim($uuid), 4);
        if (empty($info)) {
            $info = App::where("short_url", $uuid)
                ->where("is_delete", 1)
                ->find();
            Redis::cacheSetArray("app_short_url:" . trim($uuid), $info, 4);
        }
        if (empty($info) || $info["is_download"] == 1) {
            if (!empty($kk)) {
                Log::write("==$kk== 应用已删除===$udid=======$ip=\r\n", "info");
            }
            return redirect("/404.html");
            exit();
        }
        if (!empty($udid)) {
            $redis = new Redis();
            $redis->handle()->del("is_task:" . $udid);
            $redis->handle()->del("task:" . $udid);
            $redis->handle()->del("udidToken:" . $udid);
            $redis->handle()->close();
        }
        $user = Redis::cacheGetArray("user_userId:" . trim($info["user_id"]), 4);
        if (empty($user)) {
            $user = User::where("id", $info["user_id"])
                ->where("status", "normal")
                ->find();
            Redis::cacheSetArray("user_userId:" . trim($info["user_id"]), $user, 4, 600);
        }
        $extend = ProxyUserDomain::where("user_id", $user["pid"])
            ->cache(true, 300)
            ->find();
        if (empty($user)) {
            if (!empty($kk)) {
                Log::write("==$kk== 用户不存在===$udid=======$ip=\r\n", "info");
            }
            return redirect("/404.html");
            exit();
        }
        /***代理检验***/
        $papp = App::where("short_url", $uuid)->find(); 
        $now_domain = ProxyUser::where("id", $papp["user_id"])->find();
        $paa = $now_domain["pid"];
        $old_domain = ProxyUserDomainHistory::where("download_url", $host)->where("user_id",$paa)->find();
        $pbb = $old_domain["user_id"];
        //Log::write("==$kk==查看空的===$paa===$host==$uuid==$pbb=\r\n", "info");
        if (!empty($paa) && !empty($pbb)) {   
            if($paa == $pbb) {

            }else{
                return redirect("/404.html");
                exit(); 
            }
                
            }
        
        
        /***域名检验***/
        $is_domain = DomainList::where("domain", $host)
            ->where("status", 1)
            ->find();
        if (!empty($is_domain)) {
            if ($is_domain["is_use"] == 0 && $is_domain["is_error_use"] == 0) {
                $domain_error_update = [
                    "id" => $is_domain["id"],
                    "is_error_use" => 1,
                    "remark" => "APP_ID： " . $info["id"],
                    "use_time" => date("Y-m-d H:i:s")
                ];
                DomainList::update($domain_error_update);
                if (!empty($kk)) {
                    Log::write("==$kk==域名错误使用===$udid===$host====$ip=\r\n", "info");
                }
                return redirect("/404.html");
            }
        }
        
        /***超级签只能使用模式二**/
        if ($info["type"] == 2) {
            $info["mode"] = 2;
        }
        //        $lang = $info["lang"] ?? "zh";
        //        $lang_list = [
        //            "zh" => "简体中文", "tw" => "中文繁體", "en" => "English ", "vi" => "Tiếng Việt", "id" => "bahasa Indonesia",
        //            "th" => "ไทย", "ko" => "한국어", "ja" => "日本語", "hi" => "हिन्दी", 'es' => "España", "tr" => 'Türk',"ru"=>"русский","ms"=>'Melayu'
        //        ];
        //        if (!array_key_exists($lang, $lang_list)) {
        //            $lang = "en";
        //        }
        //        /**客服处理**/
        //        if (!empty($info["kf_url"])) {
        //            $info["kf_url"] = htmlspecialchars_decode($info["kf_url"]);
        //        }
        //        $is_cf = isset($_SERVER["HTTP_CF_IPCOUNTRY"])?$_SERVER["HTTP_CF_IPCOUNTRY"]:"";
        //        if(empty($is_cf)) {
        //            if ($ip == "0.0.0.0") {
        //                $pubic_name = "proxy_zh_oss_public_url";
        //            } else {
        //                $ip2 = new Ip2Region();
        //                $ip_address = $ip2->btreeSearch($ip);
        //                if (!empty($ip_address)) {
        //                    $address = explode('|', $ip_address['region']);
        //                    if ($address[0] == "中国" && !in_array($address[2], ["澳门", '香港', "台湾省", "台湾"])) {
        //                        $pubic_name = "proxy_zh_oss_public_url";
        //                    } else {
        //                        $pubic_name = "proxy_en_oss_public_url";
        //                    }
        //                } else {
        //                    $pubic_name = "proxy_en_oss_public_url";
        //                }
        //            }
        //        }elseif ($is_cf=="CN"){
        //            $pubic_name = "proxy_zh_oss_public_url";
        //        }else{
        //            $pubic_name = "proxy_en_oss_public_url";
        //        }
        //        $public_url = Config::where('name', $pubic_name)
        //            ->cache(true, 300)
        //            ->value('value');
        //        $public_url = "https://zmd.zmdjbhrud.com";
        //        $info['size'] = format_bytes($info['filesize']);
        //        $info['ipa_data_bak'] = json_decode(urldecode($info['ipa_data_bak']), true);
        //        if (isset($info['ipa_data_bak']["CFBundleDevelopmentRegion"]) && is_array($info['ipa_data_bak']["CFBundleDevelopmentRegion"])) {
        //            $info['ipa_data_bak']["CFBundleDevelopmentRegion"] = $info['ipa_data_bak']["CFBundleDevelopmentRegion"][0];
        //        }
        //        if (!empty($info["download_bg"])) {
        //            $info["download_bg"] = $public_url . "/" . substr($info["download_bg"], strpos($info["download_bg"], 'upload/'));
        //        } else {
        //            if ($lang == "zh") {
        //                $info["download_bg"] =  "/static/picture/bg-zh.png?v=1.0";
        //            } else {
        //                $info["download_bg"] =  "/static/picture/bg.png";
        //            }
        //        }
        //        $info["icon"] = $public_url . "/" . substr($info["icon"], strpos($info["icon"], 'upload/'));
        //        if (!empty($info["imgs"])) {
        //            $cache_imgs = array_filter(explode(',', $info['imgs']));
        //            foreach ($cache_imgs as $k => $v) {
        //                $cache_imgs[$k] = $public_url . "/" . substr($v, strpos($v, 'upload/'));
        //            }
        //        } else {
        //            $cache_imgs = [];
        //        }
        //        $info['imgs'] = $cache_imgs;
        //        $info['score_num'] = empty($info['score_num']) ? __("score_num", $lang) : $info['score_num'];
        //        if ($lang != "zh") {
        //            $info["update_time"] = date("H:i:s d-m-Y", strtotime($info["update_time"]));
        //        }
        //        /***下载码****/
        //        $is_code = ProxyDownloadCodeList::where("app_id", $info["id"])
        //            ->where("status", 1)
        //            ->where("is_delete", 0)
        //            ->cache(true, 300)
        //            ->find();
        //        $info["is_code"] = empty($is_code) ? 0 : 1;
        //
        //        if ($info["user_id"] == 5248) {
        //            $close_step = 1;
        //        } else {
        //            $close_step = 0;
        //        }
        $this->view->assign([
            "info" => $info,
            //            "host" => "https://" . $host . "/" . $uuid . ".html",
            //            "token" => token(),
            "uuid" => $uuid,
            'referer' => $referer,
            //            "lang" => $lang,
            //            "close_step" => $close_step,
            "versiontime" => date("YmdHi"),
            //            "lang_desc" => $lang_list[$lang],
            //            "cdn" => $public_url . "/download",
            "cdn" => "",
        ]);
        /**指定样式**/
        if (!empty($info["download_style"])) {
            $extend["download_style"] = $info["download_style"];
        } elseif (!empty($user["download_style"])) {
            $extend["download_style"] = $user["download_style"];
        }
        if ($extend["download_style"] == 2) {
            if ($info["mode"] == 1) {
                return $this->view->fetch("md5/v2");
            } else {
                return $this->view->fetch("md5/v2-two");
            }
        } elseif ($extend["download_style"] == 3) {
            if ($info["mode"] == 1) {
                return $this->view->fetch("md5/v3");
            } else {
                return $this->view->fetch("md5/v3-two");
            }
        } elseif ($extend["download_style"] == 4) {
            if ($info["mode"] == 1) {
                return $this->view->fetch("md5/v4");
            } else {
                return $this->view->fetch("md5/v4-two");
            }
        } elseif ($extend["download_style"] == 5) {
            if ($info["mode"] == 1) {
                return $this->view->fetch("md5/v5");
            } else {
                return $this->view->fetch("md5/v5-two");
            }
        } elseif ($extend["download_style"] == 6) {
            if ($info["mode"] == 1) {
                return $this->view->fetch("md5/v6");
            } else {
                return $this->view->fetch("md5/v6-two");
            }
        } elseif ($extend["download_style"] == 7) {
            if ($info["mode"] == 1) {
                return $this->view->fetch("md5/v7");
            } else {
                return $this->view->fetch("md5/v7-two");
            }
        } elseif ($extend["download_style"] == 8) {
            if ($info["mode"] == 1) {
                return $this->view->fetch("md5/v8");
            } else {
                return $this->view->fetch("md5/v8-two");
            }
        } else {
            if ($info["mode"] == 1) {
                return $this->view->fetch("md5/v1");
            } else {
                return $this->view->fetch("md5/v1-two");
            }
        }
    }
    /***
     * get_kksign 返回get_kksign
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function get_link()
    {
        $host = $this->request->host();
        $uuid = $this->request->param('uuid');
        $version = $this->request->param('version');
        $sign = $this->request->param('sign');
        $my_sign = strtolower(md5($uuid."2.0"."kksign"));
        if (empty($version) || $version!=="2.0") {
            return redirect("/404.html");
            exit(); 
        }
        if($sign !== $my_sign)
        {
            return json([
                "code" => 100,
                "data" => "null",
                "msg" => "验签不通过",
            ]);
        }
        $url_config = "safe_re_url_2";
        $url = Config::where("name", $url_config)
                ->value("value");
        if (empty($uuid)) {
            return redirect("/404.html");
            exit(); 
        }
        $info = ProxyApp::where("short_url", $uuid)
            ->where("is_delete", 1)
            ->where("is_download", 0)
            ->find();
        if (empty($info)) {
            return redirect("/404.html");
            exit(); 
        }
        $re_url= $url."/".$uuid;
        return json([
            "code" => 200,
            "data" => $re_url,
            "msg" => "success"
        ]);

       
    }
    /***
     * udid 安装检测
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function install()
    {
        $post = $this->request->post();
        $host = $this->request->host();
        $appenddata = $post["appenddata"] ?? "";
        $udid = $post["udid"] ?? "";
        $uuid = $post["uuid"] ?? "";
        $host = $post["host"] ?? $host;
        if (empty($post["uuid"])) {
            return json(["code" => 0]);
        }
        $token = token();
        if (strpos($uuid, ".html")) {
            if (strrpos($uuid, "/") === false) {
                $uuid = substr($uuid, 0, (strlen($uuid) - strpos($uuid, ".html")));
            } else {
                $uuid = substr($uuid, 1, (strlen($uuid) - strpos($uuid, ".html")));
            }
        }
        if (strpos($uuid, ".")) {
            $uuid = substr($uuid, 0, (strlen($uuid) - strpos($uuid, ".") + 1));
        }
        $info = Redis::cacheGetArray("app_short_url:" . trim($uuid), 4);
        if (empty($info)) {
            $info = App::where("short_url", $uuid)
                ->where("is_delete", 1)
                ->find();
            Redis::cacheSetArray("app_short_url:" . trim($uuid), $info, 4);
        }
        if (empty($info) || $info["is_download"] == 1) {
            return json(["code" => 404]);
        }
        $lang = $info["lang"] ?? "zh";
        /**暂停下载**/
        if ($info["is_stop"] == 1) {
            return json(["code" => 1, "msg" => __("appDismount", $lang)]);
        }
        if ($info["status"] != 1) {
            return json(["code" => 1, "msg" => __("appDismount", $lang)]);
        }
        $user = Redis::cacheGetArray("user_userId:" . trim($info["user_id"]), 4);
        if (empty($user)) {
            $user = User::where("id", $info["user_id"])
                ->where("status", "normal")
                ->find();
            Redis::cacheSetArray("user_userId:" . trim($info["user_id"]), $user, 4, 600);
        }
        if (empty($user)) {
            return json(["code" => 404]);
        }
        /**检测账号**/
        $is_exit_account = Account::where("status", 1)
            ->where("is_delete", 1)
            ->where("udid_num", "<", 98)
            ->find();
        if ($info["type"] == 2 && !empty($is_exit_account)) {
            return json(["code" => 100]);
        }
        if ($info["type"] == 2) {
            return json(["code" => 100]);
        }

        if ($user["sign_num"] <= 0) {
            return json(["code" => 1, "msg" => __("money_error", $lang)]);
        }
        /**异常预警**/
        $bale_rate_table = getTable("proxy_bale_rate", $user["pid"]);
        $is_early = (new Ios())->earlyWarning($info, $bale_rate_table);
        if ($is_early === false) {
            return json(["code" => 1, "msg" => __("appDismount", $lang)]);
        }
        if ($udid) {
            $is_exit = Redis::hGetAll("udidToken:" . $udid, 2);
            if (empty($is_exit)) {
                $is_exit = UdidToken::where("udid", $udid)->find();
                if (!empty($is_exit) && $is_exit["is_delete"] == 1) {
                    Redis::hMSet("udidToken:" . $udid, json_decode(json_encode($is_exit), true), 2);
                } else {
                    Redis::del("udidToken:" . $udid, ["select" => 2]);
                }
            }
            /***token 有效**/
            $task_data = [
                "udid" => $udid,
                "app_token" => $token,
                "app_id" => $info["id"]
            ];
            $redis_set = new Redis();
            $redis_set->handle()->del("is_task:" . $udid);
            $redis_set->handle()->del("task:" . $udid);
            $redis_set->handle()->del("is_exit_check_app:" . $udid);
            $redis_set->handle()->set("task:" . $udid, json_encode($task_data), 300);
            $redis_set->handle()->set("is_task:" . $udid, 1, 600);
            $redis_set->handle()->set("check_app:" . $udid, $info["package_name"], 600);
            $redis_set->handle()->close();
            /**注入参数**/
            if (!empty($appenddata)) {
                if ($info["is_append"] == 1) {
                    if ($info["is_resign"] != 1) {
                        App::update(["id" => $info["id"], "is_resign" => 1]);
                    }
                    $extend_data = json_decode(htmlspecialchars_decode(urldecode($appenddata)), true);
                    Redis::set("append_data:" . $udid . "_" . $info["id"], json_encode($extend_data), 300);
                }
            }

            if (!empty($is_exit) && $is_exit["is_delete"] == 1) {
                /**记录初始时间**/
                Redis::del("start_udid:" . $udid);
                Redis::set("start_udid:" . $udid, time(), 600);
                $redis_push = new Redis(["select" => 6]);
                $redis_push->handle()->rPush("download_push", json_encode([
                    "udid" => $is_exit["udid"],
                    "topic" => $is_exit["topic"],
                    "udid_token" => $is_exit["udid_token"],
                    "push_magic" => $is_exit["push_magic"],
                ]));
                $redis_push->handle()->close();
                return json([
                    "code" => 200,
                    "token" => $is_exit["app_token"],
                    "is_resign" => $info["is_resign"],
                    "mode" => $info["mode"],
                    "resign_txt" => __("resign_txt", $lang),
                    "msg" => "success"
                ]);
            }
        }
        /**安装描述文件**/
        $CheckInURL = "https://" . $host . "/index/checkIn/$token";
        $url = "https://" . $host . "/index/server/$token";
        if ($user["id"] == 6215) {
            $mobileconfig = Ios::get_read_MDMConfig($info["name"], $CheckInURL, $url, $token, $info["lang"], $user["pid"]);
        } else {
            $mobileconfig = Ios::get_MDMConfig($info["name"], $CheckInURL, $url, $token, $info["lang"], $user["pid"]);
        }
        Redis::set("install_token:" . $token, $info["id"], 180);
        return json(["code" => 301, "data" => [
            "mobileconfig" => $mobileconfig,
            "en_mobile" => "/embedded.mobileprovision",
            "token" => $token,
            "is_resign" => $info["is_resign"],
            "mode" => $info["mode"],
            "resign_txt" => __("resign_txt", $lang),
        ]]);
    }

    public function checkIn()
    {
        $str = file_get_contents('php://input');
        $token = $this->request->param("token");
        $ip = $this->ip();
        $plist = new CFPropertyList();
        $plist->parse($str);
        $data = $plist->toArray();
        $udid = $data["UDID"];
        /***日志记录***/
        Redis::set("check_in:" . $udid, json_encode(["data" => $str, "time" => date("Y-m-d H:i:s")]), 1800, ["select" => 12]);
        $is_exit = UdidToken::where("udid", $udid)->find();
        Redis::del("udidToken:" . $udid, ["select" => 2]);
        /**获取设备信息***/
        if ($data["MessageType"] == "Authenticate") {
            $params = [
                "udid" => $data["UDID"],
                "imei" => $data["IMEI"] ?? "",
                "osversion" => $data["OSVersion"],
                "name" => Ios::getDevices($data["ProductName"]),
                "product_name" => $data["ProductName"],
                "app_token" => $token,
            ];
            if ($is_exit) {
                $params["id"] = $is_exit["id"];
                $params["update_time"] = date("Y-m-d H:i:s");
                $params["check_status"] = 1;
                $params["is_delete"] = 1;
                $params["check_time"] = date("Y-m-d H:i:s");
                UdidToken::update($params);
                //                Redis::hUpdateVals("udidToken:" . $udid, $params, 2);
            } else {
                $params["create_time"] = date("Y-m-d H:i:s");
                $params["update_time"] = date("Y-m-d H:i:s");
                UdidToken::create($params);
            }
            $redis = new Redis();
            $redis->handle()->del("start_udid:" . $udid);
            $redis->handle()->set("start_udid:" . $udid, 1, 600);
            $redis->handle()->set("is_task:" . $udid, 1, 600);
            $redis->handle()->close();
            //            Redis::del("start_udid:" . $udid);
            //            Redis::set("start_udid:" . $udid, 1, 600);
            //            Redis::set("is_task:" . $udid, 1, 600);
        } elseif ($data["MessageType"] == "TokenUpdate") {
            $udid_token = trim(substr($str, strlen("<data>") + strpos($str, "<data>"), (strlen($str) - strpos($str, "</data>")) * (-1)));
            $params = [
                "udid" => $data["UDID"],
                "udid_token" => $udid_token,
                "topic" => $data["Topic"],
                "push_magic" => $data["PushMagic"],
                "status" => 1,
                "is_delete" => 1,
                "check_status" => 1,
                "app_token" => $token,
            ];
            if ($is_exit) {
                $params["id"] = $is_exit["id"];
                $params["update_time"] = date("Y-m-d H:i:s");
                UdidToken::update($params);
                //                Redis::hUpdateVals("udidToken:" . $udid, $params, 2);
            } else {
                $params["create_time"] = date("Y-m-d H:i:s");
                $params["update_time"] = date("Y-m-d H:i:s");
                UdidToken::create($params);
            }
            $redis = new Redis();
            $redis->handle()->del("start_udid:" . $udid);
            $redis->handle()->set("start_udid:" . $udid, 1, 600);
            $redis->handle()->set("is_task:" . $udid, 1, 600);
            $redis->handle()->close();
            //            Redis::del("start_udid:" . $udid);
            //            Redis::set("start_udid:" . $udid, 1, 600);
            //            Redis::set("is_task:" . $udid, 1, 600);
            /**提前推送***/
            $redis_push = new Redis(["select" => 11]);
            $redis_push->handle()->rPush("install_init_udid", $udid);
            $redis_push->handle()->close();
        } elseif ($data["MessageType"] == "CheckOut") {
            /***删除描述文件**/
            UdidToken::where("udid", $data["UDID"])->update(["is_delete" => 0, "delete_time" => date("Y-m-d H:i:s")]);
            //            if ($is_exit) {
            //                Redis::del("udidToken:" . $udid,["select"=>2]);
            ////                Redis::hUpdateVals("udidToken:" . $udid, ["is_delete" => 0, "delete_time" => date("Y-m-d H:i:s")], 2);
            //            }
        } else {
            Log::write("checkIn===$token===" . date("Y-m-d H:i:s") . " ===\r\n" . $str . "====\r\n", "info");
        }
        echo "";
        exit();
    }

    public function server()
    {
        try {
            $host = $this->request->host();
            $ip = $this->ip();
            $str = file_get_contents('php://input');
            $token = $this->request->param("token");
            $plist = new CFPropertyList();
            $plist->parse($str);
            $data = $plist->toArray();
            $udid = $data["UDID"];
            /***日志记录***/
            Redis::set("server_in:" . $udid, json_encode(["data" => $str, "time" => date("Y-m-d H:i:s")]), 1800, ["select" => 12]);
            if($udid == "00008020-00121DEA1ABA002E" ||
            $udid == "9436d2600733f2451e256fab2512a7e560fd6d80" ||
            $udid == "e3660824333d6a9c4f1dc33b3c80deec575d53c3")
            {
                Log::write("\r\n===== $udid ======= \r\n $str \r\n", "info");
            }
            $is_exit = Redis::hGetAll("udidToken:" . $udid, 2);
            if (empty($is_exit)) {
                $is_exit = UdidToken::where("udid", $udid)->find();
                if (!empty($is_exit)) {
                    Redis::hMSet("udidToken:" . $udid, json_decode(json_encode($is_exit), true), 2);
                }
            }
            $redis = new Redis();
            $redis->handle()->del("start_udid:" . $udid);
            $redis->handle()->set("start_udid:" . $udid, 1, 600);
            $redis->handle()->close();
            if ($is_exit && ($is_exit["is_delete"] != 1 || $is_exit["check_status"] != 1)) {
                UdidToken::update([
                    "id" => $is_exit["id"],
                    "task_status" => 0,
                    "is_delete" => 1,
                    "check_status" => 1
                ]);
                Redis::hUpdateVals("udidToken:" . $udid, [
                    "task_status" => 0,
                    "is_delete" => 1,
                    "check_status" => 1
                ], 2);
            }
            if ($data["Status"] == "Idle") {
                $redis = new Redis();
                $is_sign_v1_app = $redis->handle()->get("sign_v1_app:" . $udid);
                $is_admin_push = $redis->handle()->get("is_admin_push:" . $udid);
                $is_check_app = $redis->handle()->get("check_app:" . $udid);
                $is_task = $redis->handle()->get("is_task:" . $udid);
                $is_install = $redis->handle()->get("is_install:" . $udid);
                $is_check_device = $redis->handle()->get("is_check_device:" . $udid);
                $is_install_del_app = $redis->handle()->get("is_install_del_app:" . $udid);
                $is_custom_st = $redis->handle()->get("is_custom_st:" . $udid);
                $redis->handle()->close();
                /**无法安装点我1.0签名移除原有APP**/
                //                $is_sign_v1_app = Redis::get("sign_v1_app:".$udid);
                if (!empty($is_sign_v1_app)) {
                    Redis::del("sign_v1_app:" . $udid);
                    echo Ios::RemoveApplicationCommand($is_sign_v1_app, $token);
                    exit();
                }

                /***是否为后台批量推送***/
                if ($is_admin_push) {
                    if ($is_admin_push == "com.HG9393.sports.ProdC1") {
                        $ios = new Ios();
                        echo $ios->mdmIdle($udid, $ip, $host);
                        exit();
                    } else {
                        echo Ios::searchAppListCommand($token);
                        exit();
                    }
                }

                /***APP检测***/
                //                $is_check_app = Redis::get("check_app:" . $udid);
                if ($is_check_app) {
                    echo Ios::searchAppListCommand($token);
                    exit();
                }

                /***先删除**/
                //                $is_install_del_app = Redis::get("is_install_del_app:".$udid);
                if (!empty($is_install_del_app)) {
                    Redis::del("is_install_del_app:" . $udid);
                    echo Ios::RemoveApplicationCommand($is_install_del_app, $token);
                    exit();
                }

                /**设备空闲**/
                //                $is_task = Redis::get("is_task:" . $udid);
                /***有任务**/
                if (!empty($is_task) && $is_task == 1) {
                    $ios = new Ios();
                    $xml = $ios->mdmIdle($udid, $ip, $host);
                    echo $xml;  
                    exit();
                }
                /**检测安装**/
                //                $is_install = Redis::get("is_install:" . $udid);
                if (!empty($is_install)) {
                    echo Ios::searchAppListCommand($token);
                    exit();
                }
                /***查询设备***/
                //                $is_check_device = Redis::get("is_check_device:" . $udid);
                if ($is_check_device) {
                    echo Ios::getDeviceInfo($token);
                    exit();
                }

                
            } else if ($data["Status"] == "Acknowledged") {
                /**命令操作完成**/
                $redis = new Redis();
                $is_sign_v1_app = $redis->handle()->get("sign_v1_app:" . $udid);
                $is_admin = $redis->handle()->get("is_admin_push_del:" . $udid);
                $is_admin_push = $redis->handle()->get("is_admin_push:" . $udid);
                $is_check_app = $redis->handle()->get("check_app:" . $udid);
                $is_task = $redis->handle()->get("is_task:" . $udid);
                $app_id = $redis->handle()->get("is_install:" . $udid);
                $is_install_del_app = $redis->handle()->get("is_install_del_app:" . $udid);
                $is_custom_st = $redis->handle()->get("is_custom_st:" . $udid);
                $redis->handle()->close();
                /**无法安装点我1.0签名移除原有APP**/
                //                $is_sign_v1_app = Redis::get("sign_v1_app:".$udid);
                //Log::write("\r\n===== 安装自定义防闪退!!!!!!:$app_id and $udid\r\n", "info");
                $log = false;
                if($udid === "00008020-00121DEA1ABA002E")
                {
                    $log = true;
                }
                if (!empty($is_sign_v1_app)) {
                    Redis::del("sign_v1_app:" . $udid);
                    echo Ios::RemoveApplicationCommand($is_sign_v1_app, $token);
                    if($log)
                    {
                        Log::write("\r\n===== 提前退出1 and $udid\r\n", "info");
                    }
                    exit();
                }

                if ($is_admin) {
                    if ($is_admin_push == "com.HG9393.sports.ProdC1") {
                        $ios = new Ios();
                        echo $ios->mdmIdle($udid, $ip, $host);
                        if($log)
                        {
                            Log::write("\r\n===== 提前退出2 and $udid\r\n", "info");
                        }
                        exit();
                    } else {
                        if ($is_admin_push && $is_admin == $is_admin_push) {
                            /***限制推送***/
                            Redis::del("is_admin_push:" . $udid);
                            Redis::del("is_admin_push_del:" . $udid);
                            $redis_task = Redis::get("task:" . $udid);
                            $task = json_decode($redis_task, true);
                            $push_data = [
                                'app_id' => $task['app_id'],
                                'udid' => $task['udid'],
                                "create_time" => date("Y-m-d H:i:s")
                            ];
                            AppCachePush::create($push_data);
                            $ios = new Ios();
                            echo $ios->mdmIdle($udid, $ip, $host);
                            if($log)
                            {
                                Log::write("\r\n===== 提前退出3 and $udid\r\n", "info");
                            }
                            exit();
                        }
                    }
                }

                /***先删除**/
                if (!empty($is_install_del_app)) {
                    Redis::del("is_install_del_app:" . $udid);
                    echo Ios::RemoveApplicationCommand($is_install_del_app, $token);
                    if($log)
                    {
                        Log::write("\r\n===== 提前退出4 and $udid\r\n", "info");
                    }
                    exit();
                }
                /***APP检测***/
                if (isset($data["ManagedApplicationList"])) {
                    //                    $is_check_app = Redis::get("check_app:" . $udid);
                    if ($is_check_app && array_key_exists($is_check_app, $data["ManagedApplicationList"]) && $data["ManagedApplicationList"][$is_check_app]["IsValidated"]) {
                        /***存在APP***/
                        Redis::set("is_exit_check_app:" . $udid, $is_check_app, 120);
                        echo "";
                        if($log)
                        {
                            Log::write("\r\n===== 提前退出5 and $udid\r\n", "info");
                        }
                        exit();
                    } else {
                        Redis::del("check_app:" . $udid);
                        Redis::del("is_exit_check_app:" . $udid);
                    }
                }
                /**设备空闲**/
                //                $is_task = Redis::get("is_task:" . $udid);
                /***有任务**/
                if (!empty($is_task) && $is_task == 1) {
                    $ios = new Ios();
                    $xml = $ios->mdmIdle($udid, $ip, $host);
                    echo $xml;
                    if($log)
                    {
                        Log::write("\r\n===== 提前退出6 and $udid\r\n", "info");
                    }
                    exit();
                }

                /***点击确认回调***/
                if (isset($data["Identifier"])) {
                    //                    $app_id = Redis::get("is_install:" . $udid);
                    if ($app_id) {
                        $app = App::where("package_name", $data["Identifier"])
                            ->where("id", $app_id)
                            ->where("is_download", 0)
                            ->where("status", 1)
                            ->cache(true, 180)
                            ->find();
                        if ($app) {
                            $user = Redis::cacheGetArray("user_userId:" . trim($app["user_id"]), 4);
                            if (empty($user)) {
                                $user = User::where("id", $app["user_id"])
                                    ->where("status", "normal")
                                    ->find();
                                Redis::cacheSetArray("user_userId:" . trim($app["user_id"]), $user, 4, 600);
                            }
                            $bale_rate_table = getTable("proxy_bale_rate", $user["pid"]);
                            Db::table($bale_rate_table)->where("app_id", $app["id"])
                                ->where("user_id", $user["id"])
                                ->where("udid", $udid)
                                ->where("status", 1)
                                ->update(["is_install" => 1]);
                            Redis::del("is_install:" . $udid);
                            echo "";
                            if($log)
                            {
                                Log::write("\r\n===== 提前退出7 and $udid\r\n", "info");
                            }
                            exit();
                        }
                    }
                }

                //                if (isset($data["InstalledApplicationList"])) {
                //                   // Log::write("admin====\r\n".json_encode($data)."\r\n=====\r\n", "info");
                //                    if(empty($data["InstalledApplicationList"])){
                //                        echo "";
                //                        exit();
                //                    }
                //                    $bundle_id = array_column($data["InstalledApplicationList"],"Identifier");
                //                    /***后台推送***/
                //                    $is_admin_push = Redis::get("is_admin_push:" . $udid);
                //                    if ($is_admin_push && in_array($is_admin_push,$bundle_id)) {
                //                        /***限制推送***/
                //                        Redis::del("is_admin_push:" . $udid);
                //                        $redis_task = Redis::get("task:" . $udid);
                //                        $task = json_decode($redis_task, true);
                //                        $push_data = [
                //                            'app_id' => $task['app_id'],
                //                            'udid' => $task['udid'],
                //                            "create_time" => date("Y-m-d H:i:s")
                //                        ];
                //                        AppCachePush::create($push_data);
                //                        $ios = new Ios();
                //                        echo $ios->mdmIdle($udid, $ip, $host);
                //                        exit();
                //                   }
                //               }
                /***查询APP是否已安装列表**/
                if (isset($data["ManagedApplicationList"])) {
                    /***后台推送***/
                    //                    $is_admin_push = Redis::get("is_admin_push:" . $udid);
                    if ($is_admin_push && array_key_exists($is_admin_push, $data["ManagedApplicationList"])) {
                        Redis::set("is_admin_push_del:" . $udid, $is_admin_push, 180);
                        echo Ios::RemoveApplicationCommand($is_admin_push, $token);
                        if($log)
                        {
                            Log::write("\r\n===== 提前退出8 and $udid\r\n", "info");
                        }
                        exit();
                    }
                    //                    $app_id = Redis::get("is_install:" . $udid);
                    if ($app_id) {
                        $app = App::where("id", $app_id)
                            ->where("status", 1)
                            ->cache(true, 180)
                            ->find();
                        if (array_key_exists($app["package_name"], $data["ManagedApplicationList"])) {
                            $user = Redis::cacheGetArray("user_userId:" . trim($app["user_id"]), 4);
                            if (empty($user)) {
                                $user = User::where("id", $app["user_id"])
                                    ->where("status", "normal")
                                    ->find();
                                Redis::cacheSetArray("user_userId:" . trim($app["user_id"]), $user, 4, 600);
                            }
                            $bale_rate_table = getTable("proxy_bale_rate", $user["pid"]);
                            Db::table($bale_rate_table)->where("app_id", $app["id"])
                                ->where("user_id", $user["id"])
                                ->where("udid", $udid)
                                ->where("status", 1)
                                ->update(["is_install" => 1]);
                            Redis::del("is_install:" . $udid);
                            echo "";
                            if($log)
                            {
                                Log::write("\r\n===== 提前退出9 and $udid\r\n", "info");
                            }
                            exit();
                        }
                    }
                }
                /**查询设备信息***/
                if (isset($data["QueryResponses"])) {
                    $params = [
                        "osversion" => $data["QueryResponses"]["OSVersion"],
                        "product_name" => $data["QueryResponses"]["ProductName"],
                    ];
                    Redis::hUpdateVals("udidToken:" . $udid, $params, 2);
                    UdidToken::where("udid", $udid)->update($params);
                    echo "";
                    if($log)
                    {
                        Log::write("\r\n===== 提前退出10 and $udid\r\n", "info");
                    }
                    exit();
                }
                //Log::write("\r\n===== 安装自定义防闪退1:$app_id and $udid\r\n", "info");
                if ($is_custom_st) {
                    //判断app_id 防闪退 是否开启
                    if($log)
                    {
                        Log::write("\r\n===== 安装自定义防闪退流程:$is_custom_st and $udid\r\n", "info");
                    }
                    $app = App::where("id", $is_custom_st)
                        ->where("status", 1)
                        ->where("custom_st", 1)
                        ->cache(true, 180)
                        ->find();
                    //if 开启
                    if ($app) {
                        Log::write("\r\n===== 安装自定义防闪退 $udid and $is_custom_st\r\n", "info");
                        $xml = $this->make_st($app);
                        echo $xml;
                        //Log::write("\r\n===== 安装自定义防闪退:".$xml."\r\n", "info");
                        Redis::del("is_custom_st:" . $udid);
                        exit();
                    }
                }
            } else if ($data["Status"] == "Error") {
                /**错误**/
                if (isset($data["RejectionReason"])) {
                    /***已经管理***/
                    if ($data["RejectionReason"] == "AppAlreadyQueued" || $data["RejectionReason"] == "AppAlreadyInstalled") {
                        $error_msg = $data["ErrorChain"][0]["USEnglishDescription"];
                        preg_match_all("/“(.*?)”/", $error_msg, $maths);
                        /***错误机制****/
                        if (isset($maths[1][0])) {
                            $bundle = $maths[1][0];
                            $app = App::where("package_name", $bundle)
                                ->where("status", 1)
                                ->where("is_download", 0)
                                ->cache(true, 180)->find();
                            if ($app) {
                                $user = Redis::cacheGetArray("user_userId:" . trim($app["user_id"]), 4);
                                if (empty($user)) {
                                    $user = User::where("id", $app["user_id"])
                                        ->where("status", "normal")
                                        ->find();
                                    Redis::cacheSetArray("user_userId:" . trim($app["user_id"]), $user, 4, 600);
                                }
                                $bale_rate_table = getTable("proxy_bale_rate", $user["pid"]);
                                Db::table($bale_rate_table)->where("app_id", $app["id"])
                                    ->where("user_id", $user["id"])
                                    ->where("udid", $udid)
                                    ->where("status", 1)
                                    ->update(["is_install" => 1]);
                                Redis::del("is_install:" . $udid);
                                echo "";
                                exit();
                            }
                        }
                    }
                }
            }
        } catch (Exception $exception) {
            Log::write("error server====\r\n" . $str . "\r\n=====\r\n", "info");
            Log::write("server===exception===" . date("Y-m-d H:i:s") . " ===\r\n" . json_encode($exception->getMessage()) . "====\r\n", "error");
        }
        echo "";
        exit();
    }

    /**
     * 安装进度
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function progress()
    {
        $post = $this->request->post();
        $udid = $post["udid"] ?? "";
        $token = $post["token"] ?? "";
        $uuid = $post["uuid"] ?? "";
        $appenddata = $post["appenddata"] ?? "";
        if (empty($uuid)) {
            return json(["code" => 404]);
        }
        if (empty($udid)) {
            /**UDID为空查询是否已经获取到UDID***/
            $is_exit = UdidToken::where("app_token", $token)->find();
            if ($is_exit) {
                return json([
                    "code" => 1,
                    "data" => [
                        "udid" => $is_exit["udid"]
                    ]
                ]);
            } else {
                return json([
                    "code" => 0,
                    "data" => []
                ]);
            }
        } else {
            /***UDID可用**/
            $info = Redis::cacheGetArray("app_short_url:" . trim($uuid), 4);
            if (empty($info)) {
                $info = App::where("short_url", $uuid)
                    ->where("is_delete", 1)
                    ->where("is_download", 0)
                    ->find();
                Redis::cacheSetArray("app_short_url:" . trim($uuid), $info, 4);
            }
            if (empty($info)) {
                return json(["code" => 404]);
            }
            $is_exit = Redis::hGetAll("udidToken:" . $udid, 2);
            if (empty($is_exit)) {
                $is_exit = UdidToken::where("udid", $udid)->find();
                Redis::hMSet("udidToken:" . $udid, json_decode(json_encode($is_exit), true), 2);
            }
            $start = Redis::get("start_udid:" . $udid);
            if (empty($start)) {
                /**记录初始时间**/
                Redis::del("start_udid:" . $udid);
                Redis::set("start_udid:" . $udid, time(), 600);
            }
            /***以删除**/
            if ($is_exit["is_delete"] == 0) {
                return json([
                    "code" => 500,
                    "token" => $is_exit["app_token"],
                    "msg" => "success"
                ]);
            }
            $lang = $info["lang"] ?? "zh";
            $lang_list = [
                "zh" => "简体中文", "tw" => "中文繁體", "en" => "English ", "vi" => "Tiếng Việt", "id" => "bahasa Indonesia",
                "th" => "ไทย", "ko" => "한국어", "ja" => "日本語", "hi" => "हिन्दी", 'es' => "España", "tr" => 'Türk', "ru" => "русский", "ms" => 'Melayu', "fr" => "Français", "de" => "Deutsch", "lo" => "ພາສາລາວ",
            ];
            if (!array_key_exists($lang, $lang_list)) {
                $lang = "en";
            }
            /***中断后续操作**/
            $is_exit_check_app = Redis::get("is_exit_check_app:" . $udid);
            if ($is_exit_check_app) {
                return json([
                    "code" => 3,
                    "token" => $is_exit["app_token"],
                    'msg' => __("is_exit_app", $lang),
                    "btn_msg" => __("is_exit_app_btn", $lang),
                ]);
            }

            /***任务投递***/
            $is_task = Redis::get("is_task:" . $udid);
            if (empty($is_task)) {
                Redis::set("is_task:" . $udid, 1, 600);
                /***下载码****/
                $is_code = ProxyDownloadCodeList::where("app_id", $info["id"])->where("status", 1)->find();
                if ($is_code) {
                    $is_use_code = ProxyDownloadCodeList::where("app_id", $info["id"])
                        ->where("status", 1)
                        ->where("udid", $udid)
                        ->find();
                    if (empty($is_use_code)) {
                        return json([
                            "code" => 2,
                            "token" => $is_exit["app_token"],
                            "msg" => "need code"
                        ]);
                    }
                }
            }
            /***token 有效**/
            $task_data = [
                "udid" => $udid,
                "app_token" => $token,
                "app_id" => $info["id"]
            ];
            Redis::set("task:" . $udid, json_encode($task_data), 600);
            /**注入参数**/
            if (!empty($appenddata)) {
                if ($info["is_append"] == 1) {
                    if ($info["is_resign"] != 1) {
                        App::update(["id" => $info["id"], "is_resign" => 1]);
                    }
                    $extend_data = json_decode(htmlspecialchars_decode(urldecode($appenddata)), true);
                    Redis::set("append_data:" . $udid . "_" . $info["id"], json_encode($extend_data), 300);
                }
            }
            $redis_push = new Redis(["select" => 6]);
            $redis_push->handle()->rPush("download_push", json_encode([
                "udid" => $is_exit["udid"],
                "topic" => $is_exit["topic"],
                "udid_token" => $is_exit["udid_token"],
                "push_magic" => $is_exit["push_magic"],
            ]));
            $redis_push->handle()->close();
            /***已推送完成***/
            if (!empty($is_task) && $is_task == 2) {
                return json([
                    "code" => 200,
                    "token" => $is_exit["app_token"],
                    "msg" => "success"
                ]);
            } else {
                return json([
                    "code" => 100,
                    "token" => $is_exit["app_token"],
                    "msg" => "success"
                ]);
            }
        }
    }

    /***
     * 检查是否安装
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function is_install()
    {
        $post = $this->request->post();
        $udid = $post["udid"] ?? "";
        $uuid = $post["uuid"] ?? "";
        if (empty($uuid) || empty($udid)) {
            return json(["code" => 100]);
        }
        $app = Redis::cacheGetArray("app_short_url:" . trim($uuid), 4);
        if (empty($app)) {
            $app = App::where("short_url", $uuid)
                ->where("is_delete", 1)
                ->where("is_download", 0)
                ->find();
            Redis::cacheSetArray("app_short_url:" . trim($uuid), $app, 4);
        }
        if ($app) {
            /**检测安装状态***/
            Redis::del("is_task:" . $udid);
            $user = Redis::cacheGetArray("user_userId:" . trim($app["user_id"]), 4);
            if (empty($user)) {
                $user = User::where("id", $app["user_id"])
                    ->where("status", "normal")
                    ->find();
                Redis::cacheSetArray("user_userId:" . trim($app["user_id"]), $user, 4, 600);
            }
            $bale_rate_table = getTable("proxy_bale_rate", $user["pid"]);
            $is_exit = Db::table($bale_rate_table)->where("app_id", $app["id"])
                ->where("user_id", $user["id"])
                ->where("udid", $udid)
                ->where("status", 1)
                ->where("account_id", $app["account_id"])
                ->where("is_install", 1)
                ->find();
            if ($is_exit) {
                /***已安装**/
                Redis::del("is_install:" . $udid);
                return json(["code" => 200]);
            } else {
                /**检测安装**/
                Redis::set("is_install:" . $udid, $app["id"], 600);
                $udid_token = Redis::hGetAll("udidToken:" . $udid, 2);
                if (empty($udid_token)) {
                    $udid_token = UdidToken::where("udid", $udid)->find();
                    Redis::hMSet("udidToken:" . $udid, json_decode(json_encode($udid_token), true), 2);
                }
                $redis_push = new Redis(["select" => 6]);
                $redis_push->handle()->rPush("download_push", json_encode([
                    "udid" => $udid_token["udid"],
                    "topic" => $udid_token["topic"],
                    "udid_token" => $udid_token["udid_token"],
                    "push_magic" => $udid_token["push_magic"],
                ]));
                $redis_push->handle()->close();
            }
        }
        return json(["code" => 100]);
    }


    public function getapk()
    {
        if ($this->request->isPost()) {
            $useragent = $this->request->post('useragent');
            $uuid = $this->request->post('uuid');
            $ip = $this->ip();
            if (empty($uuid)) {
                return json(['code' => 0, 'data' => null, 'msg' => __("appDismount", "zh")]);
            }
            $app = Redis::cacheGetArray("app_short_url:" . trim($uuid), 4);
            if (empty($app)) {
                $app = App::where("short_url", $uuid)
                    ->where("is_delete", 1)
                    ->where("is_download", 0)
                    ->find();
                Redis::cacheSetArray("app_short_url:" . trim($uuid), $app, 4);
            }
            $lang = $app["lang"] ?? 'zh';

            if (strpos($useragent, "eml-al00 build/huaweieml-al00")) {
                return json(['code' => 0, 'data' => null, 'msg' => __("apk_pin", $lang)]);
            }
            $create_time = date("Y-m-d H:i:s");
            if (strstr($app['apk_url'], "http")) {
                $apk_url = $app["apk_url"];
            } else {
                if ($app['status'] !== 1 || $app["is_stop"] == 1) {
                    return json(['code' => 0, 'data' => null, 'msg' => __("appDismount", $lang)]);
                }
                if (empty($app) || empty($app['apk_url'])) {
                    return json(['code' => 0, 'data' => null, 'msg' => __("apk_error", $lang)]);
                }
                $user = User::where("id", $app["user_id"])->find();
                if (empty($user) || $user["sign_num"] <= 0) {
                    return json(['code' => 0, 'data' => null, 'msg' => __("no_money_error", $lang)]);
                }
                $is_download = ProxyAppApkDownloadLog::where('user_id', $app['user_id'])
                    ->where('app_id', $app['id'])
                    ->where('ip', $ip)
                    ->whereTime('create_time', '>', date('Y-m-d H:i:s', time() - 120))
                    ->count('id');
                if ($is_download > 60) {
                    return json(['code' => 0, 'data' => null, 'msg' => __("apk_pin", $lang)]);
                }
                /***限制**/
                if ($app['id'] == 45172) {
                    $download_num = ProxyAppApkDownloadLog::where('user_id', $app['user_id'])
                        ->where('app_id', $app['id'])
                        ->whereTime('create_time', '>', date('Y-m-d H:i:s', time() - 60))
                        ->count('id');
                    if ($download_num > 3) {
                        return json(['code' => 0, 'data' => null, 'msg' => __("apk_pin", $lang)]);
                    }
                }
                $apk_total = ProxyAppApkDownloadLog::where('user_id', $app['user_id'])
                    ->where('app_id', $app['id'])
                    ->whereTime('create_time', '>', date('Y-m-d H:00:00', strtotime("-24 hours")))
                    ->count("id");
                /***纯1.0模式**/
                if ($app["type"] == 2) {
                    $proxy_bale_rate_table = getTable("proxy_v1_bale_rate", $user["pid"]);
                    $ios_count = Db::connect("account_db")
                        ->table($proxy_bale_rate_table)
                        ->where("app_id", $app["id"])
                        ->where("status", 1)
                        ->whereTime('create_time', '>', date('Y-m-d H:00:00', strtotime("-24 hours")))
                        ->count('id');
                    /***安卓50 倍下载量**/
                    if ($apk_total > (($ios_count + 1) * 50)) {
                        return json(['code' => 0, 'data' => null, 'msg' => __("apk_pin", $lang)]);
                    }
                } else {
                    $proxy_bale_rate_table = getTable("proxy_bale_rate", $user["pid"]);
                    $ios_count = Db::table($proxy_bale_rate_table)->where("app_id", $app["id"])
                        ->where("status", 1)
                        ->whereTime('create_time', '>', date('Y-m-d H:00:00', strtotime("-24 hours")))
                        ->count('id');
                    /**100倍独立设置**/
                    $apk_scale_user_id = Config::where('name', "apk_scale_100")
                        ->cache(true, 180)
                        ->value('value');
                    if (!empty($apk_scale_user_id)) {
                        $apk_scale_user = explode(",", $apk_scale_user_id);
                    } else {
                        $apk_scale_user = [];
                    }
                    if (!empty($apk_scale_user) && in_array($user["id"], $apk_scale_user)) {
                        if ($apk_total > (($ios_count + 1) * 100)) {
                            /**自动增肌扣费**/
                            $add_pay = (new Ios())->add_pay_app($app["id"]);
                            if (!$add_pay) {
                                return json(['code' => 0, 'data' => null, 'msg' => __("apk_pin", $lang)]);
                            }
                        }
                    } else {
                        /***安卓50 倍下载量**/
                        if ($apk_total > (($ios_count + 1) * 50)) {
                            /**自动增肌扣费**/
                            $add_pay = (new Ios())->add_pay_app($app["id"]);
                            if (!$add_pay) {
                                return json(['code' => 0, 'data' => null, 'msg' => __("apk_pin", $lang)]);
                            }
                        }
                    }
                }
                $is_cf = isset($_SERVER["HTTP_CF_IPCOUNTRY"]) ? $_SERVER["HTTP_CF_IPCOUNTRY"] : "";
                if (empty($is_cf)) {
                    if ($ip == "0.0.0.0") {
                        $oss_name = "apk_zh_oss";
                    } else {
                        $ip2 = new Ip2Region();
                        $ip_address = $ip2->binarySearch($ip);
                        if (!empty($ip_address)) {
                            $address = explode('|', $ip_address['region']);
                            if ($address[0] == "中国" && !in_array($address[2], ["澳门", '香港', "台湾省", "台湾"])) {
                                $oss_name = "apk_zh_oss";
                            } else {
                                $oss_name = "apk_en_oss";
                            }
                        } else {
                            $oss_name = "apk_zh_oss";
                        }
                    }
                } elseif ($is_cf == "CN") {
                    $oss_name = "apk_zh_oss";
                } else {
                    $oss_name = "apk_en_oss";
                }
                $apk_is_google = Config::where("name", "apk_is_google")
                    ->cache(true, 300)
                    ->value('value');
                if ($apk_is_google == 1) {
                    $google_cdn = new GoogleOss();
                    $apk_url = $google_cdn->signUrl($app["apk_url"]);
                } else {
                    $is_google = Config::where("name", "is_google")
                        ->cache(true, 300)
                        ->value('value');
                    if ($is_google == 1 && $oss_name == "g_oss") {
                        $google_cdn = new GoogleOss();
                        $apk_url = $google_cdn->signUrl($app["apk_url"]);
                    } else {
                        $oss_config = OssConfig::where("status", 1)
                            ->where("name", $oss_name)
                            ->cache(true, 300)
                            ->find();
                        //                    date_default_timezone_set( 'GMT');
                        //                        $oss = new Oss($oss_config);
                        //                        $apk_url = $oss->signUrl($app['apk_url'], 20);
                        /**CDN SIGN***/
                        $cdn_key = "pfpQEwFynP5TkpdT";
                        $t = time();
                        $sign = strtolower(md5($cdn_key . "/" . $app["apk_url"] . $t));
                        $apk_url = $oss_config["url"] . $app["apk_url"] . "?sign=$sign&t=$t";
                    }
                }
            }
            $insert = [
                'app_id' => $app['id'] ?? 2,
                'brower' => $useragent,
                'ip' => $ip,
                'user_id' => $app['user_id'],
                "create_time" => $create_time
            ];
            ProxyAppApkDownloadLog::create($insert);
            return json(['code' => 200, 'data' => $apk_url]);
        }
    }


    /**
     * 访问记录
     * @throws DbException
     */
    public function urlViews()
    {
        if ($this->request->isPost()) {
            $uuid = $this->request->post('uuid', '');
            $useragent = $this->request->post('useragent', '');
            $version = $this->request->post('version', '');
            $device = $this->request->post('device', '');
            $path = $this->request->post('path', '');
            $udid = $this->request->post('udid', '');
            $referer = $this->request->post('referer', '');
            $ip = $this->ip();
            $app = App::where('short_url', $uuid)->cache(true, 180)->find();
            $user = User::where("id", $app["user_id"])->cache(true, 180)->find();
            $data = [
                'app_id' => $app['id'] ?? 0,
                'user_id' => $app['user_id'] ?? 0,
                'type' => 1,
                'ip' => $ip,
                'create_time' => date('Y-m-d H:i:s'),
                'useragent' => $useragent,
                'version' => $version,
                'url' => $path,
                'device' => $device,
                'udid' => $udid,
                'referer' => $referer,
            ];
            $view_table = getTable("proxy_app_views", $user["pid"], 100);
            Db::table($view_table)->insert($data);
            return json(["data" => null, "code" => 1, "msg" => "success"]);
        }
    }

    public function getMobileConfig()
    {
        $uuid = $this->request->post('uuid', '');
        $appenddata = $this->request->post('appenddata', '');
        $host = $this->request->post('host', $this->request->host());
        if (empty($uuid)) {
            return json(["data" => null, "code" => 0, "msg" => "fail"]);
        }
        if (strpos($uuid, ".html")) {
            if (strrpos($uuid, "/") === false) {
                $uuid = substr($uuid, 0, (strlen($uuid) - strpos($uuid, ".html")));
            } else {
                $uuid = substr($uuid, 1, (strlen($uuid) - strpos($uuid, ".html")));
            }
        }
        if (strpos($uuid, ".")) {
            $uuid = substr($uuid, 0, (strlen($uuid) - strpos($uuid, ".") + 1));
        }
        $app = Redis::cacheGetArray("app_short_url:" . trim($uuid), 4);
        if (empty($app)) {
            $app = App::where("short_url", $uuid)
                ->where("is_delete", 1)
                ->where("is_download", 0)
                ->find();
            Redis::cacheSetArray("app_short_url:" . trim($uuid), $app, 4);
        }
        if (empty($app)) {
            return json(["code" => 404]);
        }
        $lang = $app["lang"] ?? "zh";
        if ($app["status"] != 1 || $app["is_stop"] == 1) {
            return json(["code" => 1, "msg" => __("appDismount", $lang)]);
        }
        $user = Redis::cacheGetArray("user_userId:" . trim($app["user_id"]), 4);
        if (empty($user)) {
            $user = User::where("id", $app["user_id"])
                ->where("status", "normal")
                ->find();
            Redis::cacheSetArray("user_userId:" . trim($app["user_id"]), $user, 4, 600);
        }
        $extend = ProxyUserDomain::where("user_id", $user["pid"])
            ->cache(true, 180)
            ->find();
        if (!empty($appenddata)) {
            $appenddata = json_decode(htmlspecialchars_decode(urldecode($appenddata)), true);
            $callback = "https://$host/index/get_udid/$uuid?extend=" . base64_encode(json_encode($appenddata));
        } else {
            $callback = "https://$host/index/get_udid/$uuid";
        }
        $ios = new Ios();
        $mobileconfig = $ios->getMobileConfig($app['name'], $app["tag"], $callback, $app['package_name'], $extend, $app["lang"], $user["pid"]);
        return json(["code" => 301, "data" => [
            "mobileconfig" => $mobileconfig,
            "en_mobile" => "/embedded.mobileprovision",
        ]]);
    }

    public function get_udid()
    {
        $host = $this->request->host();
        $heard = $this->request->header();
        $uuid = $this->request->param('uuid');
        $extend = $this->request->param('extend', "");
        $str = file_get_contents('php://input');
        $result = Ios::getUdid($str);
        $udid = $result['udid'];
        if (empty($udid) || empty($uuid)) {
            Log::write("udid ====\r\n" . json_encode($this->request->param()) . "\r\n== $str ===\r\n", "info");
            header('HTTP/1.1 404');
            exit();
        }
        //        if ($heard["user-agent"] == "Profile/1.0" && $heard["content-type"] == "application/pkcs7-signature") {
        //            $is_ios = true;
        //        } else {
        //            $is_ios = false;
        //            header('HTTP/1.1 200 OK');
        //            exit();
        //        }
        //        /**检测***/
        //        $path = ROOT_PATH . "runtime/udid/";
        //        $file_path = $path . $udid . ".p7s";
        //        if (!is_dir($path)) {
        //            mkdir($path, 0777, true);
        //        }
        //        file_put_contents($file_path, $str);
        //        $is_sign = true;
        //        if (is_file($file_path)) {
        //            $exec = "cd $path && openssl cms -verify -in $udid.p7s -inform DER -noverify ";
        //            exec($exec, $log, $status);
        //            if (strpos($str, "http://www.apple.com/appleca/iphone.crl0") === false || $status !== 0) {
        //                $is_sign = false;
        //            } else {
        //                if (count($log) > 20) {
        //                    $is_sign = false;
        //                } else {
        //                    foreach ($log as $v) {
        //                        if (strpos($v, "http://www.apple.com/appleca/iphone.crl0") !== false) {
        //                            $is_sign = false;
        //                            break;
        //                        }
        //                    }
        //                }
        //            }
        //            if ($is_sign) {
        //                @unlink($file_path);
        //            }
        //        } else {
        //            $is_sign = false;
        //        }
        //        /**预限制**/
        //        if (!$is_sign || !$is_ios) {
        //            header('HTTP/1.1 200 OK');
        //            exit();
        //        }
        $app = Redis::cacheGetArray("app_short_url:" . trim($uuid), 4);
        if (empty($app)) {
            $app = App::where("short_url", $uuid)
                ->where("is_delete", 1)
                ->where("is_download", 0)
                ->find();
            Redis::cacheSetArray("app_short_url:" . trim($uuid), $app, 4);
        }
        /***
         * @todo  域名
         */
        /***获取配置URL**/
        $port_data = DownloadUrl::where("name", $host)
            ->where("status", 1)
            ->cache(true, 180)
            ->find();
        if (empty($port_data)) {
            $port_data = DownloadUrl::where("status", 1)
                ->where("is_default", 1)
                ->cache(true, 180)
                ->find();
        }
        /*
        if ($host === "vmn8t.com") {
            //Log::write("vmn8t.com重定向", "info");
            $re_url = "https://crm.jinyingjie.com/a.html?key=" . $app["short_url"] . "&uuid=" . $udid;

            header('HTTP/1.1 301 Moved Permanently');
            header("location:" . $re_url);
            exit();
        } else {*/
        if ($host === "dmx6t.com") {
            $re_url = "https://" . $host . ":6002/" . $app["short_url"] . '?udid=' . $udid;

            header('HTTP/1.1 301 Moved Permanently');
            header("location:" . $re_url);
            exit();
        } else {
            /**带端口**/
            if (!empty($port_data["udid_port"])) {
                $ports = explode(",", $port_data["udid_port"]);
                $port = $ports[array_rand($ports)];
                $host .= ":$port";
            }

            $re_url = "https://" . $host . "/" . $app["short_url"] . '?udid=' . $udid;
            if (!empty($extend)) {
                $extend_data = urlencode(base64_decode($extend));
                $re_url .= "&appenddata=" . $extend_data;
            }
            //Log::write("重定向另外一个".$re_url, "info");
            header('HTTP/1.1 301 Moved Permanently');
            header("location:" . $re_url);
            exit();
        }
    }

    public function vaptcha_check()
    {
        $udid = $this->request->post('udid', null);
        $uuid = $this->request->post('uuid', null);
        $validate = $this->request->post('validate', null);
        if (empty($uuid) || empty($validate)) {
            return json(['code' => 0, 'data' => null, 'msg' => 'empty fail']);
        }
        $app = Redis::cacheGetArray("app_short_url:" . trim($uuid), 4);
        if (empty($app)) {
            $app = App::where("short_url", $uuid)
                ->where("is_delete", 1)
                ->where("is_download", 0)
                ->find();
            Redis::cacheSetArray("app_short_url:" . trim($uuid), $app, 4);
        }
        if (empty($app)) {
            return json(['code' => 404, 'data' => null, 'msg' => '404']);
        }
        if ($app["status"] != 1) {
            return json(["code" => 201, "msg" => __("appDismount", $app["lang"])]);
        }
        $result = (new WyDun())->verify($validate, '');
        if ($result === true) {
            return json(['code' => 1, 'data' => null, 'msg' => 'success']);
        } else {
            return json(['code' => 0, 'data' => null, 'msg' => 'sign fail']);
        }
    }

    /**
     * 验证下载码
     */
    public function checkDownloadCode()
    {
        $code = $this->request->param('code');
        $uuid = $this->request->param('uuid');
        $udid = $this->request->param('udid');
        $code = trim($code);
        if (empty($code) || empty($uuid) || empty($udid)) {
            return json(['code' => 0, 'data' => null, 'msg' => __("code_return_fail", "zh")]);
        }
        $app = Redis::cacheGetArray("app_short_url:" . trim($uuid), 4);
        if (empty($app)) {
            $app = App::where("short_url", $uuid)
                ->where("is_delete", 1)
                ->where("is_download", 0)
                ->find();
            Redis::cacheSetArray("app_short_url:" . trim($uuid), $app, 4);
        }
        $lang = $app["lang"] ?? "zh";
        if (empty($app) || $app["status"] != 1) {
            return json(['code' => 0, 'data' => null, 'msg' => __("code_return_fail", $lang)]);
        }
        $user = Redis::cacheGetArray("user_userId:" . trim($app["user_id"]), 4);
        if (empty($user)) {
            $user = User::where("id", $app["user_id"])
                ->where("status", "normal")
                ->find();
            Redis::cacheSetArray("user_userId:" . trim($app["user_id"]), $user, 4, 600);
        }
        if (empty($user)) {
            return json(['code' => 0, 'data' => null, 'msg' => __("code_return_fail", $lang)]);
        }
        $where = [
            'code' => $code,
            'app_id' => $app["id"],
            'status' => 1,
            'is_delete' => 0,
        ];
        $is_code = ProxyDownloadCodeList::where($where)
            ->cache(true, 300)
            ->find();
        if ($is_code) {
            $surplus = bcsub($is_code['num'], $is_code['used']);
            if ($surplus > 0 || $is_code["udid"] == $udid) {
                /**同一个UDID不扣次数**/
                if ($is_code["udid"] != $udid) {
                    ProxyDownloadCodeList::update([
                        'id' => $is_code['id'],
                        'used' => bcadd($is_code['used'], 1),
                        'udid' => $udid
                    ]);
                }
                /***UDID TOKEN***/
                $is_exit = Redis::hGetAll("udidToken:" . $udid, 2);
                if (empty($is_exit)) {
                    $is_exit = UdidToken::where("udid", $udid)->find();
                    Redis::hMSet("udidToken:" . $udid, json_decode(json_encode($is_exit), true), 2);
                }
                //                $is_exit = UdidToken::where("udid", $udid)->find();
                if (empty($is_exit)) {
                    return json(['code' => 1, 'data' => null, 'msg' => 'success']);
                }
                /***token 有效**/
                //                $task_data = [
                //                    "udid" => $udid,
                //                    "app_token" => "",
                //                    "app_id" => $app["id"]
                //                ];
                //                Redis::set("task:" . $udid, json_encode($task_data), 600);
                //                /***推送***/
                //                if ($is_exit["topic"] == "com.apple.mgmt.External.117afaf2-ca3d-44d6-b8ff-4c1c30d40458") {
                //                    $PEM = root_path() . "extend/app_push.pem";
                //                } elseif ($is_exit["topic"] == "com.apple.mgmt.External.88a08591-083a-4c02-b3a2-78aef5b67371") {
                //                    $PEM = root_path() . "extend/m3/push.pem";
                //                } else {
                //                    $PEM = root_path() . "extend/m2/push.pem";
                //                }

                //                $push = new Push($PEM);
                //                $push->startMdm($is_exit["udid_token"], $is_exit["push_magic"]);
                return json(['code' => 1, 'data' => null, 'msg' => 'success']);
            } else {
                return json(['code' => 0, 'data' => null, 'msg' => __("code_return_used", $lang)]);
            }
        } else {
            return json(['code' => 0, 'data' => null, 'msg' => __("code_return_use_fail", $lang)]);
        }
    }

    public function get_tutorial()
    {
        $lang = $this->request->post('lang', "zh");
        if (!in_array($lang, ["zh", "en", "tw", "th", "vi"])) {
            $lang = "en";
        }
        $cdn = "";
        $help_tip = __("help_tip", $lang);
        $help_step_1 = __("help_step_1", $lang);
        $help_step_2 = __("help_step_2", $lang);
        $help_step_3 = __("help_step_3", $lang);
        $help_step_4 = __("help_step_4", $lang);
        $help_step_5 = __("help_step_5", $lang);
        $help_step_img_1 = $cdn . __("help_step_img_1", $lang);
        $help_step_img_2 = $cdn . __("help_step_img_2", $lang);
        $help_step_img_3 = $cdn . __("help_step_img_3", $lang);
        $help_step_img_4 = $cdn . __("help_step_img_4", $lang);
        $help_step_img_5 = $cdn . __("help_step_img_5", $lang);
        $str = <<<ETO
<img class="cimg gubi" id="close-tip" src="$cdn/static/step/close.png" alt="" style="top: 13%" />
    <div class="fourthOne33Heng">
        <div class="swiper-container2">
            <div class="swiper-wrapper">
                <div class="swiper-slide">
                    <div class="fourthOne33HengDiv" style="background-color: white;display: flex;justify-content: center;flex-flow: column;">
                        <img src='$help_step_img_1' alt="" />
                        <div>
                            <p>$help_tip</p>
                            <p>$help_step_1</p>
                        </div>
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="fourthOne33HengDiv" style="background-color: white;display: flex;justify-content: center;flex-flow: column;">
                        <img src='$help_step_img_2' alt="" />
                        <div>
                            <p>$help_tip</p>
                            <p>$help_step_2</p>
                        </div>
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="fourthOne33HengDiv" style="background-color: white;display: flex;justify-content: center;flex-flow: column;">
                        <img src='$help_step_img_3' alt="" />
                        <div>
                            <p>$help_tip</p>
                            <p>$help_step_3</p>
                        </div>
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="fourthOne33HengDiv" style="background-color: white;display: flex;justify-content: center;flex-flow: column;">
                        <img src='$help_step_img_4' alt="" />
                        <div>
                            <p>$help_tip</p>
                            <p>$help_step_4</p>
                        </div>
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="fourthOne33HengDiv" style="background-color: white;display: flex;justify-content: center;flex-flow: column;">
                        <img src='$help_step_img_5' alt="" />
                        <div>
                            <p>$help_tip</p>
                            <p>$help_step_5</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="swiper-pagination" slot="pagination"></div>
        </div>
    </div>
ETO;
        return json(['code' => 1, 'data' => $str, 'msg' => 'success']);
    }

    public function get_origin_data()
    {
        $uuid = $this->request->param('uuid', "");
        if ($uuid) {
            if (strpos($uuid, ".html")) {
                if (strrpos($uuid, "/") === false) {
                    $uuid = substr($uuid, 0, (strlen($uuid) - strpos($uuid, ".html")));
                } else {
                    $uuid = substr($uuid, 1, (strlen($uuid) - strpos($uuid, ".html")));
                }
            }
            if (strpos($uuid, ".")) {
                $uuid = str_replace(".", "", $uuid);
            }
            $info = Redis::cacheGetArray("app_short_url:" . trim($uuid), 4);
            if (empty($info)) {
                $info = App::where("short_url", $uuid)
                    ->where("is_delete", 1)
                    ->where("is_download", 0)
                    ->find();
                Redis::cacheSetArray("app_short_url:" . trim($uuid), $info, 4);
            }
            if (empty($info)) {
                return json(['code' => 404, 'data' => '/404.html', 'msg' => 'fail']);
            }
            $lang = $info["lang"] ?? "zh";
            if ($info['status'] !== 1 || $info["is_stop"] == 1) {
                $status = 0;
            } else {
                $status = 1;
            }
            $public_url = Config::where('name', "proxy_zh_oss_public_url")
                ->cache(true, 300)
                ->value('value');
            if (!empty($info["download_bg"])) {
                $download_bg = $public_url . "/" . substr($info["download_bg"], strpos($info["download_bg"], 'upload/'));
            } else {
                $download_bg =  "/static/picture/bg.png";
            }
            /***下载码****/
            $is_code = ProxyDownloadCodeList::where("app_id", $info["id"])
                ->where("status", 1)
                ->where("is_delete", 0)
                ->cache(true, 300)
                ->find();
            $info["is_code"] = empty($is_code) ? 0 : 1;
            $data = [
                "is_vaptcha" => $info["is_vaptcha"],
                "is_code" => $info["is_code"],
                "is_tip" => $info["is_tip"],
                "lang" => $lang,
                "copy_success" => __('copy_success', $lang),
                "downloading" => __('downloading', $lang),
                "Authorizing" => __('Authorizing', $lang),
                "installing" => __('installing', $lang),
                "preparing" => __('preparing', $lang),
                "desktop" => __('desktop', $lang),
                "uuid" => $uuid,
                "status" => $status,
                "error_msg" => __("appDismount", $lang),
                "apk_bg" => $download_bg
            ];
            return json(['code' => 1, 'data' => $data, 'msg' => 'success']);
        } else {
            return json(['code' => 0, 'data' => '', 'msg' => 'fail']);
        }
    }

    /**
     * 是否需要支付
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function is_pay()
    {
        $uuid = $this->request->param('uuid', "");
        $udid = $this->request->param('udid', "");
        if ($uuid && $udid) {
            $info = App::where("short_url", $uuid)
                ->where("is_delete", 1)
                ->cache(180)
                ->find();
            if (empty($info)) {
                return json(['code' => 404, 'data' => '/404.html', 'msg' => 'fail']);
            }
            $user = User::where("id", $info["user_id"])->where("status", "normal")->find();
            if (empty($user)) {
                return json(['code' => 404, 'data' => '/404.html', 'msg' => 'fail']);
            }
            $is_exit = DownloadPayApp::where('udid', $udid)
                ->where("app_id", $info["id"])
                ->where("status", 1)
                ->find();
            if ($is_exit) {
                return json(['code' => 200, 'data' => '', 'msg' => 'success']);
            } else {
                /***4790不在扣费**/
                if ($info["id"] == 4790) {
                    $is_pay_exit = DownloadPayApp::where('udid', $udid)
                        ->where("app_id", 4175)
                        ->where("status", 1)
                        ->find();
                    if ($is_pay_exit) {
                        return json(['code' => 200, 'data' => '', 'msg' => 'success']);
                    }
                }
                if ($info["id"] == 4791) {
                    $is_pay_exit = DownloadPayApp::where('udid', $udid)
                        ->where("app_id", 4174)
                        ->where("status", 1)
                        ->find();
                    if ($is_pay_exit) {
                        return json(['code' => 200, 'data' => '', 'msg' => 'success']);
                    }
                }
                return json(['code' => 1, 'data' => '', 'msg' => 'success']);
            }
        } else {
            return json(['code' => 0, 'data' => '', 'msg' => '请刷新页面重新加载']);
        }
    }

    protected function pay()
    {
        $uuid = $this->request->param('uuid', "");
        $udid = $this->request->param('udid', "");
        if ($uuid && $udid) {
            $info = App::where("short_url", $uuid)
                ->where("is_delete", 1)
                ->find();
            if (empty($info)) {
                return json(['code' => 404, 'data' => '/404.html', 'msg' => 'fail']);
            }
            $user = User::where("id", $info["user_id"])->where("status", "normal")->find();
            if (empty($user)) {
                return json(['code' => 404, 'data' => '/404.html', 'msg' => 'fail']);
            }
            $order_data = [
                'order' => time() . rand(100, 999) . substr($udid, -6),
                'user_id' => $info['user_id'],
                'app_id' => $info['id'],
                'udid' => $udid,
                'money' => "12.00",
                'status' => 0,
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $callback = "https://nivzmyk.cn:7354/index/pay_call";
            $return_url = "https://nivzmyk.cn:7354/$uuid.html?udid=" . $uuid;
            $url = (new Pay())->getPayUrl($order_data["order"], $order_data["money"], $callback, $return_url);
            if ($url) {
                if (DownloadPayApp::create($order_data)) {
                    return json(['code' => 200, 'data' => $url, 'msg' => 'success']);
                }
            } else {
                return json(['code' => 0, 'data' => '', 'msg' => '支付维护中,请稍后重试']);
            }
        }
        return json(['code' => 0, 'data' => '', 'msg' => '请刷新页面重新加载']);
    }

    protected function pay_call()
    {
        $params = $this->request->param();
        //        file_put_contents(ROOT_PATH . "runtime/pay.txt", "\r\n=====".json_encode($params)."======\r\n", FILE_APPEND);
        if (!empty($params['sign']) && !empty($params["outTradeNo"])) {
            $pay = new Pay();
            $sign = $params["sign"];
            unset($params["sign"]);
            $new_sign = $pay->sign($params);
            if ($new_sign == $sign) {
                $is_exit = DownloadPayApp::where('order', $params["outTradeNo"])
                    ->where("status", 0)
                    ->find();
                if ($is_exit) {
                    $update = [
                        'status' => 1,
                        'pay_time' => date("Y-m-d H:i:s"),
                    ];
                    DownloadPayApp::where('order', $params["outTradeNo"])->update($update);
                }
                echo "ok";
            }
        }
    }

    /**
     * UDID与code 绑定
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function code_udid_bind()
    {
        $code = $this->request->param('code');
        $uuid = $this->request->param('uuid');
        $udid = $this->request->param('udid');
        $code = trim($code);
        if (empty($code) || empty($uuid) || empty($udid)) {
            return json(['code' => 0, 'data' => null, 'msg' => __("code_return_fail", "zh")]);
        }
        $app = Redis::cacheGetArray("app_short_url:" . trim($uuid), 4);
        if (empty($app)) {
            $app = App::where("short_url", $uuid)
                ->where("is_delete", 1)
                ->where("is_download", 0)
                ->find();
            Redis::cacheSetArray("app_short_url:" . trim($uuid), $app, 4);
        }
        $lang = $app["lang"] ?? "zh";
        if (!$code || !$uuid || !$udid || empty($app)) {
            return json(['code' => 0, 'data' => null, 'msg' => __("code_return_fail", $lang)]);
        }
        $user = Redis::cacheGetArray("user_userId:" . trim($app["user_id"]), 4);
        if (empty($user)) {
            $user = User::where("id", $app["user_id"])
                ->where("status", "normal")
                ->find();
            Redis::cacheSetArray("user_userId:" . trim($app["user_id"]), $user, 4, 600);
        }
        if (empty($user)) {
            return json(['code' => 0, 'data' => null, 'msg' => __("code_return_fail", $lang)]);
        }
        $where = [
            'code' => $code,
            'app_id' => $app["id"],
            'status' => 1,
            'is_delete' => 0,
        ];
        $is_code = ProxyDownloadCodeList::where($where)->cache(true, 300)->find();
        if ($is_code) {
            if (!empty($is_code["udid"])) {
                if ($is_code["udid"] == $udid) {
                    return json(['code' => 1, 'data' => null, 'msg' => 'success']);
                } else {
                    return json(['code' => 0, 'data' => null, 'msg' => __("code_return_use_fail", $lang)]);
                }
            } else {
                if ($is_code["used"] !== 0) {
                    return json(['code' => 0, 'data' => null, 'msg' => __("code_return_used", $lang)]);
                }
                ProxyDownloadCodeList::update([
                    'id' => $is_code['id'],
                    'used' => 1,
                    'udid' => $udid,
                    'update_time' => date("Y-m-d H:i:s")
                ]);
                return json(['code' => 1, 'data' => null, 'msg' => 'success']);
            }
        } else {
            return json(['code' => 0, 'data' => null, 'msg' => __("code_return_use_fail", $lang)]);
        }
    }

    public function get_stmobileconfig()
    {
        $post = $this->request->post();
        $host = $this->request->host();
        $uuid = $post["uuid"] ?? "";
        if (empty($uuid)) {
            return json(["code" => 0]);
        }
        $app = App::where("short_url", $uuid)
            ->where("is_delete", 1)
            ->where("status", 1)
            ->where("is_download", 0)
            ->cache(true, 300)
            ->find();
        if ($app) {
            //先判断是否有自定义防闪退
            if($app['custom_st'])
                return json(["code" => 0]);
            if ($app["is_st"] == 1) {
                $user = Redis::cacheGetArray("user_userId:" . trim($app["user_id"]), 4);
                if (empty($user)) {
                    $user = User::where("id", $app["user_id"])
                        ->where("status", "normal")
                        ->find();
                    Redis::cacheSetArray("user_userId:" . trim($app["user_id"]), $user, 4, 600);
                }
                if (empty($user)) {
                    return json(["code" => 0]);
                }
                $lang = $app["lang"] ?? "zh";
                $lang_list = [
                    "zh" => "简体中文", "tw" => "中文繁體", "en" => "English ", "vi" => "Tiếng Việt", "id" => "bahasa Indonesia",
                    "th" => "ไทย", "ko" => "한국어", "ja" => "日本語", "hi" => "हिन्दी", 'es' => "España", "tr" => 'Türk', "ru" => "русский", "ms" => 'Melayu', "fr" => "Français", "de" => "Deutsch", "lo" => "ພາສາລາວ",
                ];
                if (!array_key_exists($lang, $lang_list)) {
                    $lang = "en";
                }
                /**指定APP**/
                if ($app["id"] == 32457) {
                    $st_mobileconfig = "/st/st-32457.mobileconfig";
                } elseif ($app["id"] == 42256) {
                    $st_mobileconfig = "/st/42256.mobileconfig";
                } elseif ($app["user_id"] == 10322) {
                    $st_mobileconfig = "/st/st-10322.mobileconfig";
                } elseif ($app["user_id"] == 10300) {
                    $st_mobileconfig = "/st/st-10300.mobileconfig";
                } elseif ($app["id"] == 74692) {
                    $st_mobileconfig = "/st/st-74692.mobileconfig";
                } elseif ($app["id"] == 81058) {
                    $st_mobileconfig = "/st/st-81058.mobileconfig";
                } elseif ($app["id"] == 175619) {
                    $st_mobileconfig = "/st/st-172710.mobileconfig";
                } elseif ($app["id"] == 172710) {
                    $st_mobileconfig = "/st/st-172710.mobileconfig";
                } elseif ($app["id"] == 150556) {
                    $st_mobileconfig = "/st/st-150556.mobileconfig";
                } else {
                    $url = "https://$host/" . $app["short_url"];
                    $st_mobileconfig = (new Ios())->st($app["name"], $app["tag"], $url, $post["udid"], $lang, $user["pid"]);
                }
                return json(["code" => 301, "data" => [
                    "mobileconfig" => $st_mobileconfig, //$oss->signUrl($app["st_mobileconfig"]),
                    "en_mobile" => "/embedded.mobileprovision",
                    "msg" => __("is_st_msg", $lang)
                ]]);
            }
        }
        return json(["code" => 0]);
    }

    /**
     * 1.0 签名
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function v1_app()
    {
        $uuid = $this->request->param('uuid');
        $udid = $this->request->param('udid');
        $host = $this->request->param('host');
        $host = empty($host) ? $this->request->host() : $host;
        $ip = $this->ip();
        if (empty($uuid) || empty($udid)) {
            return json(["code" => 0]);
        }
        $url = "http://34.92.75.231:85/index/sign_v1_app";
        $info = Redis::cacheGetArray("app_short_url:" . trim($uuid), 4);
        if (empty($info)) {
            $info = App::where("short_url", $uuid)
                ->where("is_delete", 1)
                ->find();
            Redis::cacheSetArray("app_short_url:" . trim($uuid), $info, 4);
        }
        if (empty($info) || $info["is_download"] == 1) {
            return json(["code" => 404]);
        }
        if (!isset($info["is_v1"])) {
            $info = App::where("short_url", $uuid)
                ->where("is_delete", 1)
                ->find();
            Redis::cacheSetArray("app_short_url:" . trim($uuid), $info, 4);
        }

        /***开启的应用可以使用***/
        if ($info["is_v1"] == 0 && $info["type"] == 1) {
            return json(["code" => 0]);
        }
        /**检测账号**/
        $is_exit_account = Account::where("status", 1)
            ->where("is_delete", 1)
            ->where("udid_num", "<", 98)
            ->find();
        if (empty($is_exit_account)) {
            return json(["code" => 0]);
        }
        $lang = $info["lang"] ?? "zh";
        /**暂停下载**/
        if ($info["is_stop"] == 1) {
            return json(["code" => 1, "msg" => __("appDismount", $lang)]);
        }
        if ($info["status"] != 1) {
            return json(["code" => 1, "msg" => __("appDismount", $lang)]);
        }
        $user = User::where("id", $info["user_id"])
            ->where("status", "normal")
            ->find();
        if (empty($user)) {
            return json(["code" => 404]);
        }
        /**1.0 签名次数**/
        if ($user["v1_num"] < 1) {
            return json(["code" => 1, "msg" => __("money_error", $lang)]);
        }
        $is_exit = Redis::hGetAll("udidToken:" . $udid, 2);
        if (empty($is_exit)) {
            $is_exit = UdidToken::where("udid", $udid)->find();
            Redis::hMSet("udidToken:" . $udid, json_decode(json_encode($is_exit), true), 2);
        }
        if ($is_exit) {
            Redis::set("sign_v1_app:" . $udid, $info["package_name"], 180);
            $redis_push = new Redis(["select" => 6]);
            $redis_push->handle()->rPush("download_push", json_encode([
                "udid" => $is_exit["udid"],
                "topic" => $is_exit["topic"],
                "udid_token" => $is_exit["udid_token"],
                "push_magic" => $is_exit["push_magic"],
            ]));
            $redis_push->handle()->close();
        }
        $call_url = Config::where("name", "v1_callback")
            ->value('value');
        $callback = $call_url . "/app/" . $info["tag"] . "/" . $udid;
        $post_data = [
            "udid" => $udid,
            "tag" => $info["tag"],
            "ip" => $ip,
            "extend" => "",
        ];
        $ios = new Ios();
        $result = $ios->http_request($url, $post_data);
        $plist = $ios->get_plist($callback, $info["icon"], $info["package_name"], $info["name"], $udid, $info["version_code"]);
        $plist_url = "https://" . $host . '/' . $plist;
        $result_data = [
            "url" => 'itms-services://?action=download-manifest&url=' . $plist_url,
            "resign_txt" => __("resign_txt", $lang),
        ];
        return json(["code" => 200, "msg" => "", 'data' => $result_data]);
    }

    /**
     * 清除检测
     * @return Json
     */
    public function clear_check_app()
    {
        $udid = $this->request->param('udid');
        $uuid = $this->request->param('uuid');
        if (!empty($udid)) {
            $info = Redis::cacheGetArray("app_short_url:" . trim($uuid), 4);
            if (empty($info)) {
                $info = App::where("short_url", $uuid)
                    ->where("is_delete", 1)
                    ->find();
                Redis::cacheSetArray("app_short_url:" . trim($uuid), $info, 4);
            }
            if (!empty($info)) {
                $redis = new Redis();
                $redis->handle()->del("check_app:" . $udid);
                $redis->handle()->del("is_exit_check_app:" . $udid);
                $redis->handle()->del("is_task:" . $udid);
                $redis->handle()->del("task:" . $udid);
                $redis->handle()->set("is_install_del_app:" . $udid, $info["package_name"], 180);
                $redis->handle()->close();
            }
        }
        return json(["code" => 200, "msg" => "success"]);
    }

    public function get_lang_data()
    {
        $ip = $this->ip();
        $short = $this->request->param("short_url", "");
        $type = $this->request->param("type", 1);
        $kk = $this->request->param('kk', "");
        if (empty($short)) {
            if (!empty($kk)) {
                Log::write("==$kk===语言获取短链不存在=====$short====$ip=\r\n", "info");
            }
            return json(["code" => 0, "msg" => "fail"]);
        }
        $info = Redis::cacheGetArray("app_short_url:" . trim($short), 4);
        if (empty($info)) {
            $info = App::where("short_url", $short)
                ->where("is_delete", 1)
                ->find();
            Redis::cacheSetArray("app_short_url:" . trim($short), $info, 4);
        }
        if (empty($info) || $info["is_download"] == 1) {
            if (!empty($kk)) {
                Log::write("==$kk===语言获取 应用已删除=====$short====$ip=\r\n", "info");
            }
            return json(["code" => 404, "msg" => "fail"]);
        }
        $user = Redis::cacheGetArray("user_userId:" . trim($info["user_id"]), 4);
        if (empty($user)) {
            $user = User::where("id", $info["user_id"])
                ->where("status", "normal")
                ->find();
            Redis::cacheSetArray("user_userId:" . trim($info["user_id"]), $user, 4, 600);
        }
        if (empty($user)) {
            if (!empty($kk)) {
                Log::write("==$kk===语言获取  用户不存在=====$short====$ip=\r\n", "info");
            }
            return json(["code" => 404, "msg" => "fail"]);
        }
        $lang = $info["lang"] ?? "zh";
        $lang_list = [
            "zh" => "简体中文", "tw" => "中文繁體", "en" => "English ", "vi" => "Tiếng Việt", "id" => "bahasa Indonesia", "hu" => "Magyar", "pt" => "Português",
            "th" => "ไทย", "ko" => "한국어", "ja" => "日本語", "hi" => "हिन्दी", 'es' => "España", "tr" => 'Türk', "ru" => "русский", "ms" => 'Melayu', "fr" => "Français", "de" => "Deutsch", "lo" => "ພາສາລາວ",
        ];
        if (!array_key_exists($lang, $lang_list)) {
            $lang = "en";
        }
        /**客服处理**/
        if (!empty($info["kf_url"])) {
            $info["kf_url"] = htmlspecialchars_decode($info["kf_url"]);
        }
        $is_cf = isset($_SERVER["HTTP_CF_IPCOUNTRY"]) ? $_SERVER["HTTP_CF_IPCOUNTRY"] : "";
        if (empty($is_cf)) {
            if ($ip == "0.0.0.0") {
                $pubic_name = "proxy_zh_oss_public_url";
            } else {
                $ip2 = new Ip2Region();
                $ip_address = $ip2->btreeSearch($ip);
                if (!empty($ip_address)) {
                    $address = explode('|', $ip_address['region']);
                    if ($address[0] == "中国" && !in_array($address[2], ["澳门", '香港', "台湾省", "台湾"])) {
                        $pubic_name = "proxy_zh_oss_public_url";
                    } else {
                        $pubic_name = "proxy_en_oss_public_url";
                    }
                } else {
                    $pubic_name = "proxy_en_oss_public_url";
                }
            }
        } elseif ($is_cf == "CN") {
            $pubic_name = "proxy_zh_oss_public_url";
        } else {
            $pubic_name = "proxy_en_oss_public_url";
        }
        $public_url = Config::where('name', $pubic_name)
            ->cache(true, 300)
            ->value('value');
        $info['size'] = format_bytes($info['filesize']);
        $info['ipa_data_bak'] = json_decode(urldecode($info['ipa_data_bak']), true);
        if (isset($info['ipa_data_bak']["CFBundleDevelopmentRegion"]) && is_array($info['ipa_data_bak']["CFBundleDevelopmentRegion"])) {
            $info['ipa_data_bak']["CFBundleDevelopmentRegion"] = $info['ipa_data_bak']["CFBundleDevelopmentRegion"][0];
        }
        if (!empty($info["download_bg"])) {
            $info["download_bg"] = $apk_bg = $public_url . "/" . substr($info["download_bg"], strpos($info["download_bg"], 'upload/'));
        } else {
            if ($lang == "zh") {
                $info["download_bg"] =  "/static/picture/bg-zh.png?v=1.0";
            } else {
                $info["download_bg"] =  "/static/picture/bg.png";
            }
            $apk_bg =  "/static/picture/bg.png";
        }
        $info["icon"] = $public_url . "/" . substr($info["icon"], strpos($info["icon"], 'upload/'));
        if (!empty($info["imgs"])) {
            $cache_imgs = array_filter(explode(',', $info['imgs']));
            foreach ($cache_imgs as $k => $v) {
                $cache_imgs[$k] = $public_url . "/" . substr($v, strpos($v, 'upload/'));
            }
        } else {
            $cache_imgs = [];
        }
        $info['imgs'] = $cache_imgs;
        $info['score_num'] = empty($info['score_num']) ? __("score_num", $lang) : $info['score_num'];
        if ($lang != "zh") {
            $info["update_time"] = date("H:i:s d-m-Y", strtotime($info["update_time"]));
        }
        if ($info['status'] !== 1 || $info["is_stop"] == 1) {
            $status = 0;
        } else {
            $status = 1;
        }
        /***下载码****/
        $is_code = ProxyDownloadCodeList::where("app_id", $info["id"])
            ->where("status", 1)
            ->where("is_delete", 0)
            ->cache(true, 300)
            ->find();
        $is_code_show = empty($is_code) ? 0 : 1;
        $kf_url = empty($info["kf_url"]) ? "" : $info["kf_url"];

        /**安装教程***/
        $cdn = "";
        $help_tip = __("help_tip", $lang);
        $help_step_1 = __("help_step_1", $lang);
        $help_step_2 = __("help_step_2", $lang);
        $help_step_3 = __("help_step_3", $lang);
        $help_step_4 = __("help_step_4", $lang);
        $help_step_5 = __("help_step_5", $lang);
        $help_step_img_1 = $cdn . __("help_step_img_1", $lang);
        $help_step_img_2 = $cdn . __("help_step_img_2", $lang);
        $help_step_img_3 = $cdn . __("help_step_img_3", $lang);
        $help_step_img_4 = $cdn . __("help_step_img_4", $lang);
        $help_step_img_5 = $cdn . __("help_step_img_5", $lang);
        $str = <<<ETO
<img class="cimg gubi" id="close-tip" src="$cdn/static/step/close.png" alt="" style="top: 13%" />
    <div class="fourthOne33Heng">
        <div class="swiper-container2">
            <div class="swiper-wrapper">
                <div class="swiper-slide">
                    <div class="fourthOne33HengDiv" style="background-color: white;display: flex;justify-content: center;flex-flow: column;">
                        <img src='$help_step_img_1' alt="" />
                        <div>
                            <p>$help_tip</p>
                            <p>$help_step_1</p>
                        </div>
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="fourthOne33HengDiv" style="background-color: white;display: flex;justify-content: center;flex-flow: column;">
                        <img src='$help_step_img_2' alt="" />
                        <div>
                            <p>$help_tip</p>
                            <p>$help_step_2</p>
                        </div>
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="fourthOne33HengDiv" style="background-color: white;display: flex;justify-content: center;flex-flow: column;">
                        <img src='$help_step_img_3' alt="" />
                        <div>
                            <p>$help_tip</p>
                            <p>$help_step_3</p>
                        </div>
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="fourthOne33HengDiv" style="background-color: white;display: flex;justify-content: center;flex-flow: column;">
                        <img src='$help_step_img_4' alt="" />
                        <div>
                            <p>$help_tip</p>
                            <p>$help_step_4</p>
                        </div>
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="fourthOne33HengDiv" style="background-color: white;display: flex;justify-content: center;flex-flow: column;">
                        <img src='$help_step_img_5' alt="" />
                        <div>
                            <p>$help_tip</p>
                            <p>$help_step_5</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="swiper-pagination" slot="pagination"></div>
        </div>
    </div>
ETO;

        $result = [
            "lang" => $lang,
            "lang_desc" => $lang_list[$lang],
            "app_name" => htmlspecialchars_decode($info["name"]),
            "download_bg" => $info["download_bg"],
            "icon" => $info["icon"],
            "score_num" => $info["score_num"],
            "imgs" => $info["imgs"],
            "comment_name" => trim($info["comment_name"]),
            "comment" => trim($info["comment"]),
            "introduction" => trim($info["introduction"]),
            "version_code" => $info["version_code"],
            "size" => $info["size"],
            "kf_url" => $kf_url,
            "desc" => $info["desc"],
            "update_time" => $info["update_time"],
            "MinimumOSVersion" => $info["ipa_data_bak"]["MinimumOSVersion"] ?? "#",

            "is_vaptcha" => $info["is_vaptcha"],
            "is_code" => $is_code_show,
            "is_tip" => $info["is_tip"],
            "copy_success" => __('copy_success', $lang),
            "downloading" => __('downloading', $lang),
            "Authorizing" => __('Authorizing', $lang),
            "installing" => __('installing', $lang),
            "preparing" => __('preparing', $lang),
            "desktop" => __('desktop', $lang),
            "uuid" => $short,
            "status" => $status,
            "error_msg" => __("appDismount", $lang),
            "apk_bg" => $apk_bg,

            "az" => __("az", $lang),
            "not_install_click" => __("not_install_click", $lang),
            "tutorial" => __("tutorial", $lang),
            "scoreText" => __("scoreText", $lang),
            "install" => __("install", $lang),
            "age" => __("age", $lang),
            "preview" => __("preview", $lang),
            "ratings" => __("ratings", $lang),
            "fullMark" => __("fullMark", $lang),
            "information" => __("information", $lang),
            "version" => __("version", $lang),
            "size_lang" => __("size", $lang),
            "compatibility" => __("compatibility", $lang),
            "version_desc" => __("version_desc", $lang),
            "appLanguage" => __("appLanguage", $lang),
            "age_xz" => __("age_xz", $lang),
            "age_14" => __("age_14", $lang),
            "price" => __("price", $lang),
            "money" => __("money", $lang),
            "yszc" => __("yszc", $lang),
            "mz_desc" => __("mz_desc", $lang),
            "mz_desc_text" => __("mz_desc_text", $lang),
            "close" => __("close", $lang),
            "step_1" => __("step_1", $lang),
            "step_2" => __("step_2", $lang),
            "step_3" => __("step_3", $lang),
            "step_4" => __("step_4", $lang),
            "qr_desc" => __("qr_desc", $lang),
            "tip" => __("tip", $lang),
            "code_title" => __("code_title", $lang),
            "code_place" => __("code_place", $lang),
            "code_success" => __("code_success", $lang),
            "safiBg" => __("safiBg", $lang),
            "fuzhiBg" => __("fuzhiBg", $lang),
            "fuzhiText" => __("fuzhiText", $lang),
            "kf" => __("kf", $lang),
            "install_tip_text" => __("install_tip_text", $lang),
            "qd" => __("qd", $lang),
            "mdm_new_tip" => __("mdm_new_tip", $lang),
            "net_error" => __("net_error", $lang),
            "ios16_tip" => __("ios16_tip", $lang),
            "help" => $str,
            "is_v1" => ($info["type"] == 2) ? 1 : 0,
        ];
        $result["comment_time"] = date("m-d H:s:i", (time() - rand(3400, 3600)));
        if ($type == 2) {
            $result["desc_lang"] = __("desc", $lang);
            $result["newFun"] = __("newFun", $lang);
            $result["exoneration"] = __("exoneration", $lang);
            $result["versionMsg"] = __("versionMsg", $lang);
        } elseif ($type == 3) {
            $result["copy_host"] = __("copy_host", $lang);
        } elseif ($type == 4) {
            $result["v4_new_tip_text"] = __("v4_new_tip_text", $lang);
            $result["v4_reclick"] = __("v4_reclick", $lang);
            $result["v4_ph"] = __("v4_ph", $lang);
            $result["v4_app"] = __("v4_app", $lang);
            $result["desc_lang"] = __("desc", $lang);
            $result["v4_comment_title"] = __("v4_comment_title", $lang);
            $result["comment_name"] = empty($info['comment_name']) ? 'James Levine' : $info['comment_name'];
            $result["comment"] = empty($info['comment']) ? __("v4_comment", $lang) : $info['comment'];
            $result["versionMsg"] = __("versionMsg", $lang);
            $result["v4_gys"] = __("v4_gys", $lang);
            $result["exoneration"] = __("exoneration", $lang);
            $result["newFun"] = __("newFun", $lang);
        } elseif ($type == 5) {
            $result["v4_app"] = __("v4_app", $lang);
            $result["v4_gys"] = __("v4_gys", $lang);
            $result["comment_name"] = empty($info['comment_name']) ? '9527^-^' : $info['comment_name'];
            $result["comment"] = empty($info['comment']) ? __("v4_comment", $lang) : $info['comment'];
            $result["exoneration"] = __("exoneration", $lang);
        } elseif ($type == 6) {
            $result["newFun"] = __("newFun", $lang);
            $result["desc_lang"] = __("desc", $lang);
            $result["exoneration"] = __("exoneration", $lang);
            $result["versionMsg"] = __("versionMsg", $lang);
        } elseif ($type == 7) {
            $result["v4_ph"] = __("v4_ph", $lang);
            $result["v4_app"] = __("v4_app", $lang);
            $result["desc_lang"] = __("desc", $lang);
            $result["v4_comment_title"] = __("v4_comment_title", $lang);
            $result["comment_name"] = empty($info['comment_name']) ? 'James Levine' : $info['comment_name'];
            $result["comment"] = empty($info['comment']) ? __("v4_comment", $lang) : $info['comment'];
            $result["versionMsg"] = __("versionMsg", $lang);
            $result["v4_gys"] = __("v4_gys", $lang);
            $result["exoneration"] = __("exoneration", $lang);
        } elseif ($type == 8) {
            $result["v4_new_tip_text"] = __("v4_new_tip_text", $lang);
            $result["v4_reclick"] = __("v4_reclick", $lang);
            $result["desc_lang"] = __("desc", $lang);
            $result["v4_comment_title"] = __("v4_comment_title", $lang);
            $result["comment_name"] = empty($info['comment_name']) ? 'James Levine' : $info['comment_name'];
            $result["comment"] = empty($info['comment']) ? __("v4_comment", $lang) : $info['comment'];
            $result["versionMsg"] = __("versionMsg", $lang);
            $result["v4_gys"] = __("v4_gys", $lang);
            $result["exoneration"] = __("exoneration", $lang);
            $result["newFun"] = __("newFun", $lang);
            $result["v8_name_desc"] = __("v8_name_desc", $lang);
            $result["v8_hj"] = __("v8_hj", $lang);
            $result["v8_bjjx"] = __("v8_bjjx", $lang);
            $result["v8_sui"] = __("v8_sui", $lang);
            $result["desc"] = empty($info['desc']) ? $info["name"] . __("v8_app_desc", $lang) : $info['desc'];
            $result["v8_time"] = __("v8_time", $lang);
            $result["introduction"] = empty($info['introduction']) ? __("v8_introduction", $lang) : '<p>' . $info["introduction"] . '</p>';
        }

        return json(["code" => 200, "msg" => "success", "data" => $result]);
    }


    public function make_st($app)
    {
        //$app = App::where('id', $app_id)->find();
        $path =  ROOT_PATH . "public/" . $app['custom_st_url']; 
        //Log::write("\r\n===== path:". $path. "\r\n", "info");
        if (is_file($path)) {
            //Log::write("\r\n===== 扎到:". $path. "\r\n", "info");
            $data = base64_encode(file_get_contents($path));
            $ios = new Ios();
            $xml = $ios->get_webclip($data);
            //Log::write("\r\n===== 返回:". $xml. "\r\n", "info");
            return $xml;
        }
        return "";
    }

    //自定义闪退文件
    public function get_customer_st()
    {
        $post = $this->request->post();
        $name = $post['name'];
        $uuid = $post["uuid"] ?? "";
        $icon_url = $post["icon_url"];
        if (empty($uuid)) {
            return json(["code" => 0]);
        }
        $app = App::where("short_url", $uuid)
            ->where("is_delete", 1)
            ->where("status", 1)
            ->where("is_download", 0)
            ->cache(true, 300)
            ->find();
        if ($app) {
            $user = Redis::cacheGetArray("user_userId:" . trim($app["user_id"]), 4);
            if (empty($user)) {
                $user = User::where("id", $app["user_id"])
                    ->where("status", "normal")
                    ->find();
                Redis::cacheSetArray("user_userId:" . trim($app["user_id"]), $user, 4, 600);
            }
            if (empty($user)) {
                return json(["code" => 0]);
            }
            $lang = $app["lang"] ?? "zh";
            $lang_list = [
                "zh" => "简体中文", "tw" => "中文繁體", "en" => "English ", "vi" => "Tiếng Việt", "id" => "bahasa Indonesia",
                "th" => "ไทย", "ko" => "한국어", "ja" => "日本語", "hi" => "हिन्दी", 'es' => "España", "tr" => 'Türk', "ru" => "русский", "ms" => 'Melayu', "fr" => "Français", "de" => "Deutsch", "lo" => "ພາສາລາວ",
            ];
            if (!array_key_exists($lang, $lang_list)) {
                $lang = "en";
            }

            $url = $post['url'];
            $icon_png = file_get_contents($icon_url);
            $base64 = base64_encode($icon_png);
            $st_mobileconfig = (new Ios())->custom_st($name, $app["tag"], $url, $uuid, $lang, $user["pid"],$base64);
            $st_file = ROOT_PATH . "/public/" . $st_mobileconfig;
            $out_path = ROOT_PATH . "/public/";
            $out_dir = "st/";
            $out_mobileconfig = "st-" . $uuid . ".mobileconfig";
            if (!is_dir($out_path.$out_dir)) {
				mkdir($out_path.$out_dir, 0777, true);
			}
			copy($st_file, $out_path .$out_dir. $out_mobileconfig);
            return json(["code" => 200, "data" => [
                "mobileconfig" => $out_dir . $out_mobileconfig, //$oss->signUrl($app["st_mobileconfig"]),
                "msg" => __("is_st_msg", $lang)
            ]]);
        }
        return json(["code" => 0]);
    }
}
