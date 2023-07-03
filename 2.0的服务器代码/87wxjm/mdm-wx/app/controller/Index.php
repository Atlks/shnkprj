<?php
namespace app\controller;

use app\BaseController;
use app\lib\GoogleOss;
use app\lib\Ios;
use app\lib\Ip2Region;
use app\lib\Oss;
use app\model\Config;
use app\model\DownloadUrl;
use app\model\OssConfig;
use app\model\ProxyApp;
use app\model\ProxyAppApkDownloadLog;
use app\model\ProxyUser;
use app\model\ProxyUserDomain;
use app\model\WxAppView;
use app\model\WxRehost;
use think\facade\Db;
use think\facade\Log;
use think\facade\View;

class Index extends BaseController
{
    public function index()
    {
        $uuid = $this->request->param("uuid");
        $kk = $this->request->param("kk",'');
        $host = $this->request->host();
        $referer = $this->request->server("HTTP_REFERER");
        $ip = $this->ip();
        $info = ProxyApp::where("short_url", $uuid)
            ->where("is_delete", 1)
            ->where("is_download", 0)
            ->cache(true,300)
            ->find();
        if (empty($info)) {
            return redirect("/404.html");
        }
        if(!empty($kk)){
            Log::write("===" . json_encode($_SERVER) . " ===$ip====\r\n", "info");
        }
        $user = ProxyUser::where("id", $info["user_id"])
            ->where("status","normal")
            ->cache(true,300)
            ->find();
        if(empty($user)){
            return redirect("/404.html");
        }
        $wx_data  =[
            "app_id"=>$info["id"],
            "user_id"=>$info["user_id"],
            "domain"=>$host,
            "ip"=>$ip,
            "referer"=>$referer,
            "create_time"=>date("Y-m-d H:i:s")
        ];
        if(!empty($referer)){
            WxAppView::create($wx_data);
        }
        $lang = $info["lang"] ?? "zh";
        $lang_list = [
            "zh" => "简体中文", "tw" => "中文繁體", "en" => "English ", "vi" => "Tiếng Việt", "id" => "bahasa Indonesia", "th" => "ไทย",
            "ko" => "한국어", "ja" => "日本語", "hi" => "हिन्दी","pt"=>"português",'es' => "España","fr"=>"Français","de"=>"Deutsch"
        ];
        if (!array_key_exists($lang, $lang_list)) {
            $lang = "en";
        }
        $ip2 = new Ip2Region();
        $ip_address = $ip2->memorySearch($ip);
        $address = explode('|', $ip_address['region']);
        if ($address[0] == "中国" && !in_array($address[2], ["澳门", '香港', "台湾省","台湾"])) {
            $public_url = Config::where('name', 'proxy_zh_oss_public_url')
                ->cache(true,600)
                ->value('value');
        } else {
            $public_url = Config::where('name', 'proxy_en_oss_public_url')
                ->cache(true,600)
                ->value('value');
        }
        $info['size'] = format_bytes($info['filesize']);
        if(!empty($info["download_bg"])){
            $info["download_bg"] = $public_url . "/" . substr($info["download_bg"], strpos($info["download_bg"], 'upload/'));
        }else{
            $info["download_bg"] = "/static/picture/bg.png";
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
        if($lang!="zh"){
            $info["update_time"]= date("H:i:s d-m-Y",strtotime($info["update_time"]));
        }
        $extend = ProxyUserDomain::where("user_id", $user["pid"])
            ->cache(true, 300)
            ->find();
        $re_host = WxRehost::where("wx_host",$host)->find();
        if(!empty($re_host)){
            $url = $re_host["re_host"];
        }else {
            $url = $extend["download_url"];
            /***获取配置URL**/
            $port_data = DownloadUrl::where("name", $url)
                ->where("status", 1)
                ->cache(true, 180)
                ->find();
            if (empty($port_data)) {
                $port_data = DownloadUrl::where("status", 1)
                    ->where("is_default")
                    ->cache(true, 180)
                    ->find();
            }
            /**带端口**/
            if (!empty($port_data["wx_port"])) {
                $ports = explode(",", $port_data["wx_port"]);
                $port = $ports[array_rand($ports)];
                $url = "$url:$port";
            }
        }
        $view = View::engine(config("view.type"));
        /***下载码****/
        $view->assign([
            "info" => $info,
            'referer' => $referer,
            'uuid' => $uuid,
            "lang" => $lang,
            "lang_desc"=>$lang_list[$lang],
            "re_url" => "https://" . $url . "/" . $uuid . ".html",
        ]);

        return $view->fetch("ajax-md5-old");
        if($extend["download_style"]==2){
            return $view->fetch("v2");
        }elseif ($extend["download_style"]==4){
            return $view->fetch("v4");
        }else{
            return $view->fetch("wx");
        }
    }

    public function getapk()
    {
        if ($this->request->isPost()) {
            $useragent = $this->request->post('useragent');
            $uuid = $this->request->post('uuid');
            $ip = $this->ip();
            $app = ProxyApp::where('short_url', $uuid)
                ->where('is_delete', 1)
                ->where("is_download", 0)
                ->where('status', 1)
                ->cache(true,300)
                ->find();
            $lang = $app["lang"] ?? 'zh';

            if(strpos($useragent,"eml-al00 build/huaweieml-al00")){
                return json(['code' => 0, 'data' => null, 'msg' => __("apk_pin", $lang)]);
            }
            $create_time = date("Y-m-d H:i:s");
            if (strstr($app['apk_url'], "http")) {
                $apk_url = $app["apk_url"];
            } else {
                if ($app['status']!==1||$app["is_stop"]==1) {
                    return json(['code' => 0, 'data' => null, 'msg' => __("appDismount", $lang)]);
                }
                if (empty($app) || empty($app['apk_url'])) {
                    return json(['code' => 0, 'data' => null, 'msg' => __("apk_error", $lang)]);
                }
                $user = ProxyUser::where("id", $app["user_id"])
                    ->cache(true,300)
                    ->find();
                if (empty($user) || $user["sign_num"] <= 0) {
                    return json(['code' => 0, 'data' => null, 'msg' => __("money_error", $lang)]);
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
                if($app['id']==45172){
                    $download_num = ProxyAppApkDownloadLog::where('user_id', $app['user_id'])
                        ->where('app_id', $app['id'])
                        ->whereTime('create_time', '>', date('Y-m-d H:i:s', time() - 120))
                        ->count('id');
                    if($download_num > 3){
                        return json(['code' => 0, 'data' => null, 'msg' => __("apk_pin", $lang)]);
                    }
                }
                $apk_total = ProxyAppApkDownloadLog::where('user_id', $app['user_id'])
                    ->where('app_id', $app['id'])
                    ->whereTime('create_time', '>', date('Y-m-d H:00:00', strtotime("-24 hours")))
                    ->count("id");
                /***纯1.0模式**/
                if($app["type"]==2){
                    $proxy_bale_rate_table = getTable("proxy_v1_bale_rate", $user["pid"]);
                    $ios_count = Db::connect("account_db")->table($proxy_bale_rate_table)
                        ->where("app_id", $app["id"])
                        ->where("status", 1)
                        ->whereTime('create_time', '>', date('Y-m-d H:00:00', strtotime("-24 hours")))
                        ->count('id');
                    /***安卓50 倍下载量**/
                    if ($apk_total > (($ios_count + 1) * 50)) {
                        return json(['code' => 0, 'data' => null, 'msg' => __("apk_pin", $lang)]);
                    }
                }else {
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
                        if ($apk_total > (($ios_count + 1) * 50)) {
                            /**自动增肌扣费**/
                            $add_pay = (new Ios())->add_pay_app($app["id"]);
                            if (!$add_pay) {
                                return json(['code' => 0, 'data' => null, 'msg' => __("apk_pin", $lang)]);
                            }
                        }
                    }
                }
                $is_cf = isset($_SERVER["HTTP_CF_IPCOUNTRY"])?$_SERVER["HTTP_CF_IPCOUNTRY"]:"";
                if(empty($is_cf)) {
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
                }elseif ($is_cf=="CN"){
                    $oss_name = "apk_zh_oss";
                }else{
                    $oss_name = "apk_en_oss";
                }
                $apk_is_google = Config::where("name", "apk_is_google")
                    ->cache(true, 300)
                    ->value('value');
                if($apk_is_google==1){
                    $google_cdn = new GoogleOss();
                    $apk_url = $google_cdn->signUrl($app["apk_url"]);
                }else {
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
//                        $oss = new Oss($oss_config);
//                        $apk_url = $oss->signUrl($app['apk_url'], 20);
                        $cdn_key = "pfpQEwFynP5TkpdT";
                        $t = time();
                        $sign = strtolower(md5($cdn_key."/".$app["apk_url"].$t));
                        $apk_url = $oss_config["url"].$app["apk_url"]."?sign=$sign&t=$t";
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
            return json(['code' => 200, 'data' => $apk_url,"time"=>date("Y-m-d H:i:s")]);
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
            $app = ProxyApp::where('short_url', $uuid)->find();
            $user = ProxyUser::where("id", $app["user_id"])->find();
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
//            Db::table($view_table)->insert($data);
            return json(["data" => null, "code" => 1, "msg" => "success"]);
        }
    }

    public function get_data(){
        $host = $this->request->host();
        $uuid = $this->request->param('uuid');
        $ip = $this->request->ip();
        if (empty($uuid)) {
            return json(['code' => 0, 'data' => null,"time"=>date("Y-m-d H:i:s")]);
        }
        if (strpos($uuid, ".html")) {
            if (strrpos($uuid, "/") === false) {
                $uuid = substr($uuid, 0, (strlen($uuid) - strpos($uuid, ".html")));
            } else {
                $uuid = substr($uuid, 1, (strlen($uuid) - strpos($uuid, ".html")));
            }
        }
//        if (strpos($uuid, ".")) {
//            $uuid = str_replace(".", "", $uuid);
//        }
        $uuid = get_short_url($uuid);
        $info = ProxyApp::where("short_url", $uuid)
            ->where("is_delete", 1)
            ->where("is_download", 0)
            ->cache(true,300)
            ->find();
        if (empty($info)) {
            return json(['code' => 0, 'data' => null,"time"=>date("Y-m-d H:i:s")]);
        }
        $user = ProxyUser::where("id", $info["user_id"])
            ->where("status","normal")
            ->cache(true,300)
            ->find();
        if(empty($user)){
            return json(['code' => 0, 'data' => null,"time"=>date("Y-m-d H:i:s")]);
        }
        $lang = $info["lang"] ?? "zh";
        $lang_list = [
            "zh" => "简体中文", "tw" => "中文繁體", "en" => "English ", "vi" => "Tiếng Việt", "id" => "bahasa Indonesia", "th" => "ไทย",
            "ko" => "한국어", "ja" => "日本語", "hi" => "हिन्दी","pt"=>"português",'es' => "España","fr"=>"Français","de"=>"Deutsch"
        ];
        if (!array_key_exists($lang, $lang_list)) {
            $lang = "en";
        }
        $ip2 = new Ip2Region();
        $ip_address = $ip2->binarySearch($ip);
        $address = explode('|', $ip_address['region']);
        if ($address[0] == "中国" && !in_array($address[2], ["澳门", '香港', "台湾省","台湾"])) {
            $public_url = Config::where('name', 'proxy_zh_oss_public_url')
                ->cache(true,600)
                ->value('value');
        } else {
            $public_url = Config::where('name', 'proxy_en_oss_public_url')
                ->cache(true,600)
                ->value('value');
        }
//        $info['size'] = format_bytes($info['filesize']);
//        if(!empty($info["download_bg"])){
//            $info["download_bg"] = $public_url . "/" . substr($info["download_bg"], strpos($info["download_bg"], 'upload/'));
//        }else{
//            $info["download_bg"] = "/static/picture/bg.png";
//        }
        $logo = $public_url . "/" . substr($info["icon"], strpos($info["icon"], 'upload/'));
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
//        if($lang!="zh"){
//            $info["update_time"]= date("H:i:s d-m-Y",strtotime($info["update_time"]));
//        }

        $wx_md5_url = Config::where("name","wx_md5_url")
            ->cache(true,600)
            ->value("value");
        if(!empty($wx_md5_url)){
            $re_url = $wx_md5_url;
        }else {
            $extend = ProxyUserDomain::where("user_id", $user["pid"])
                ->cache(true, 300)
                ->find();
            $re_host = WxRehost::where("wx_host", $host)->find();
            if (!empty($re_host)) {
                $url = $re_host["re_host"];
            } else {
                $url = $extend["download_url"];
                /***获取配置URL**/
                $port_data = DownloadUrl::where("name", $url)
                    ->where("status", 1)
                    ->cache(true, 180)
                    ->find();
                if (empty($port_data)) {
                    $port_data = DownloadUrl::where("status", 1)
                        ->where("is_default")
                        ->cache(true, 180)
                        ->find();
                }
                /**带端口**/
                if (!empty($port_data["wx_port"])) {
                    $ports = explode(",", $port_data["wx_port"]);
                    $port = $ports[array_rand($ports)];
                    $url = "$url:$port";
                }
            }
            $re_url = "https://" . $url ;
        }
        $result = [
            "app_name"=>htmlspecialchars_decode($info["name"]),
            "logo"=>$logo,
            "uuid"=>$uuid,
            "az"=>__("az",$lang),
            "exoneration"=>__("exoneration",$lang),
            "apk_do"=>__("apk_do",$lang),
            "apk_tips"=>__("apk_tips",$lang),
//            "re_url" => $re_url. "/" . $uuid . ".html",
            "re_url" => $re_url. "/" . $uuid ,
        ];
        return json(['code' => 200, 'data' => $result,"time"=>date("Y-m-d H:i:s")]);
    }


}
