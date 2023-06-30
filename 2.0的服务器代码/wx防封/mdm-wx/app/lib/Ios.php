<?php


namespace app\lib;


use app\lib\Oss;
use app\model\App;
use app\model\AutoAppRefush;
use app\model\Config;
use app\model\OssConfig;
use app\model\ProxyUserDomain;
use app\model\UdidToken;
use app\model\User;
use fast\Random;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

class Ios
{

    public static function get_MDMConfig($app_name,$checkIn,$server,$token,$lang="zh"){
        $path = ROOT_PATH."/runtime/mdm/".Random::alnum()."/";
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $out_name = $token.".mobileconfig";
        $return_path = "cache/".date("Ymd")."/";
        $out_path = ROOT_PATH."/public/".$return_path;
        if(!is_dir($out_path)){
            mkdir($out_path, 0777, true);
        }
        $lang_list = [
            'zh'=>[
                1=>"信誉平台值得信赖苹果授权安全可靠",
                2=>"安装防掉签文件后返回浏览器",
            ],
            "tw"=>[
                1=>"信譽平台值得信賴蘋果授權安全可靠",
                2=>"安裝防掉簽文件後返回瀏覽器",
            ],
            "en" => [
                1=>"The reputation platform is trustworthy and authorized by Apple to be safe and reliable",
                2=>"Return to the browser after installing the anti-sign off file",
            ],
            /**越南***/
            "vi" => [
                1 => "Nền tảng danh tiếng đáng tin cậy và được Apple ủy quyền là an toàn và đáng tin cậy",
                2 => "Quay lại trình duyệt sau khi cài đặt tệp chống đăng xuất",
            ],
            /**印尼**/
            "id" => [
                1 => "Platform reputasi dapat dipercaya dan diotorisasi oleh Apple untuk menjadi aman dan terpercaya",
                2 => "Kembali ke browser setelah menginstal file anti-sign off",
            ],
            /***泰语**/
            "th" => [
                1 => "แพลตฟอร์มชื่อเสียงได้รับความไว้วางใจและได้รับอนุญาตจาก Apple ว่าปลอดภัยและเชื่อถือได้",
                2 => "กลับไปที่เบราว์เซอร์หลังจากติดตั้งไฟล์ป้องกันการลงชื่อเข้าใช้",
            ],
            /**韩语**/
            "ko" => [
                1 => "평판 플랫폼은 신뢰할 수 있으며 Apple이 안전하고 신뢰할 수 있도록 승인했습니다.",
                2 => "안티 사인 오프 파일을 설치 한 후 브라우저로 돌아 가기",
            ],
            /**日语**/
            "ja" => [
                1 =>"レピュテーションプラットフォームは信頼でき、Appleによって安全で信頼できることが承認されています",
                2 =>"サインオフ防止ファイルをインストールした後、ブラウザに戻る",
            ],
            "hi" => [
                1 => "प्रतिष्ठा मंच भरोसेमंद और अधिकृत Apple द्वारा सुरक्षित और विश्वसनीय है",
                2 => "एंटी-साइन ऑफ फ़ाइल इंस्टॉल करने के बाद ब्राउज़र पर लौटें",
            ]
        ];
        if(array_key_exists($lang,$lang_list)){
            $lang_sub =$lang_list[$lang];
        }else{
            $lang_sub =$lang_list["zh"];
        }
        $a = $lang_sub[1];
        $b = $lang_sub[2];

        exec("rm $path/*.mobileconfig");
        /***
         * @todo 测试证书
         */
        $str = file_get_contents(ROOT_PATH."/extend/m2/MDM.mobileconfig");
//        $str = file_get_contents(ROOT_PATH."/extend/MDM.mobileconfig");
        $new_str = str_replace(["App-Name","youCheckInURL","youServerURL","信誉平台值得信赖苹果授权安全可靠","安装防掉签文件后返回浏览器"],[$app_name,$checkIn,$server,$a,$b],$str);
        file_put_contents($path."/mdm.mobileconfig",$new_str);
        copy(ROOT_PATH."/extend/cert/ios.crt",$path."ios.crt");
        copy(ROOT_PATH."/extend/cert/ios.key",$path."ios.key");
        copy(ROOT_PATH."/extend/cert/ios.pem",$path."ios.pem");
        $shell = "cd $path && openssl smime -sign -in mdm.mobileconfig -out $out_name -signer ios.crt -inkey ios.key -certfile ios.pem -outform der -nodetach";
        exec($shell, $log, $status);
        if ($status < 5) {
            copy($path . $out_name, $out_path . $out_name);
            $del = new self();
            $del->delDir($path);
            return $return_path . $out_name;
        } else {
            $del = new self();
            $del->delDir($path);
            return false;
        }
    }


    /**
     * 删除文件
     * @param $path
     * @return int
     */
    public function delDir($path)
    {
        if (!is_dir($path)) {
            return 0;
        }
        $handle = dir($path);
        while (false !== ($entry = $handle->read())) {
            if (($entry != ".") && ($entry != "..")) {
                if (is_file($path . "/" . $entry)) {
                    unlink($path . "/" . $entry);
                } else {
                    $this->delDir($path . "/" . $entry);
                }
            }
        }
        $handle->close();
        rmdir($path);
    }

    /***任务空闲**/
    public function mdmIdle($udid,$ip=""){
        $redis_task = Redis::get("task:".$udid);
        $task = json_decode($redis_task,true);
        $task["ip"]= $ip;
        $is_exit = UdidToken::where("udid",$udid)->find();
        if(empty($is_exit)){
            return "";
        }
        if(empty($task["app_id"])){
            return "";
        }
        $token = $is_exit["app_token"];
        $app = App::where("id",$task["app_id"])
            ->where("is_delete",1)
            ->find();
        $user = User::where("id",$app["user_id"])
            ->where("status","normal")
            ->find();
        if(empty($app)||empty($user)){
            /***应用不存在**/
            return "";
        }
        if($user["sign_num"]<=0){
            /**余额不足**/
            return '';
        }
        $domain = ProxyUserDomain::where("user_id",$user["pid"])->find();
        $ip2 = new Ip2Region();
        $ip_address = $ip2->binarySearch($ip);
        $address = explode('|',$ip_address['region']);
        if($address[0]=="中国" && !in_array($address[2],["澳门",'香港',"台湾省","台湾"])){
            $task['is_overseas'] = 10;
        }else{
            $task['is_overseas']=20;
        }
        /**回调地址***/
        $callback_url = Config::where("name","callback_url")->value("value");
        $callback = $callback_url."/app/".$app["tag"]."/$udid";
        $bale_rate_table = getTable("proxy_bale_rate",$user["pid"]);
        $is_bale_rate = Db::table($bale_rate_table)->where("app_id",$app["id"])
            ->where("user_id",$user["id"])
            ->where("udid",$udid)
            ->where("status",1)
            ->where("account_id",$app["account_id"])
            ->find();
        /**是否有刷率***/
        $is_auto =AutoAppRefush::where("app_id",$app["id"])->where("status",1)->find();
        /***
         * @todo  测试
         *
        */
//        $domain['download_url'] = "sj88991.com";
        if($is_bale_rate){
            Db::table($bale_rate_table)->where("id",$is_bale_rate["id"])->update([
                "update_time"=>date("Y-m-d H:i:s"),
                "is_overseas"=>$task["is_overseas"],
                'account_id' => $app['account_id'],
                'osversion' => $is_exit["osversion"],
                'product_name' => $is_exit["product_name"],
            ]);
            App::where("id",$app["id"])->inc("download_num",1)->update();
            $plist = $this->get_plist($callback,$app["icon"],$app["package_name"],$app["name"],$udid);
            if($is_auto){
                $auto_url = Config::where("name","auto_amount")->cache(true,300)->value("value");
                $this->http_request($auto_url,["app_id"=>$app["id"]]);
            }
            UdidToken::update(["id"=>$is_exit["id"],"task_status"=>2]);
            $plist = "https://".$domain['download_url'].'/'.$plist;
            /***任务已接收**/
            Redis::set("is_task:" . $udid,2,600);
            Redis::del("task:".$udid);
//            Cache::delete("is_task:".$udid);
            return $this->getInstallAppXml($plist,$token);
        }else{
            $bale_rate=[
                'app_id' => $app['id'],
                'udid' => $udid,
                'resign_udid' => $udid,
                'user_id' => $user['id'],
                'rate' => $user['rate'],
                'pid' => $user['pid'],
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
                'account_id' => $app['account_id'],
                'ip' => $ip,
                'device' => $is_exit["name"],
                'osversion' => $is_exit["osversion"],
                'product_name' => $is_exit["product_name"],
                'sign_num' => 1,
                'is_overseas' => $task["is_overseas"],
            ];
            Db::table($bale_rate_table)->insert($bale_rate);
            User::where("id",$user["id"])->dec("sign_num",1)->update();
            App::where("id",$app["id"])->inc("download_num",1)->inc("pay_num",1)->update();
            $plist = $this->get_plist($callback,$app["icon"],$app["package_name"],$app["name"],$udid);
            if($is_auto){
                $auto_url = Config::where("name","auto_amount")->cache(true,300)->value("value");
                $this->http_request($auto_url,["app_id"=>$app["id"]]);
            }
            UdidToken::update(["id"=>$is_exit["id"],"task_status"=>2]);
            $plist = "https://".$domain['download_url'].'/'.$plist;
            /***任务已接收**/
            Redis::set("is_task:" . $udid,2,600);
            Redis::del("task:".$udid);
//            Cache::delete("is_task:".$udid);
            return $this->getInstallAppXml($plist,$token);
        }
    }


    public function tokenUpdateApp($token,$udid,$ip){
        $app_id = Cache::get($token);
        if(empty($app_id)){
            /***无任务**/
            return "";
        }else{
            Cache::delete($token);
        }
        $is_exit = UdidToken::where("udid",$udid)->find();
        if(empty($is_exit)||$is_exit["app_token"]!=$token){
            return "";
        }
        $app = App::where("id",$app_id)
            ->where("is_delete",1)
            ->find();
        $user = User::where("id",$app["user_id"])
            ->where("status","normal")
            ->find();
        if(empty($app)||empty($user)){
            /***应用不存在**/
            return "";
        }
        if($user["sign_num"]<=0){
            /**余额不足**/
            return '';
        }
        $domain = ProxyUserDomain::where("user_id",$user["pid"])->find();
        $ip2 = new Ip2Region();
        $ip_address = $ip2->binarySearch($ip);
        $address = explode('|',$ip_address['region']);
        if($address[0]=="中国" && !in_array($address[2],["澳门",'香港',"台湾省","台湾"])){
            $is_overseas = 10;
        }else{
            $is_overseas=20;
        }
        /**回调地址***/
        $callback = "https://".$domain['download_url']."/app/".$app["tag"]."/$udid";
        UdidToken::update(["id"=>$is_exit["id"],"task_status"=>1]);
        $bale_rate_table = getTable("proxy_bale_rate",$user["pid"]);
        $is_bale_rate = Db::table($bale_rate_table)->where("app_id",$app["id"])
            ->where("user_id",$user["id"])
            ->where("udid",$udid)
            ->where("status",1)
            ->where("account_id",$app["account_id"])
            ->find();
        if($is_bale_rate){
            Db::table($bale_rate_table)->where("id",$is_bale_rate["id"])->update([
                "update_time"=>date("Y-m-d H:i:s"),
                "is_overseas"=>$is_overseas
            ]);
            /***已付费***/
            $plist = $this->get_plist($callback,$app["icon"],$app["package_name"],$app["name"],$udid);
            $plist = "https://".$domain['download_url'].'/'.$plist;
            return $this->getInstallAppXml($plist,$token);
        }else{
            $bale_rate=[
                'app_id' => $app['id'],
                'udid' => $udid,
                'resign_udid' => $udid,
                'user_id' => $user['id'],
                'rate' => $user['rate'],
                'pid' => $user['pid'],
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
                'account_id' => $app['account_id'],
                'ip' => $ip,
                'device' => $is_exit["name"],
                'sign_num' => 1,
                'is_overseas' => $is_overseas,
            ];
            $download  =[
                'account_id' => 0,
                'app' => $app['name'],
                'tag' => $app['tag'],
                'user_id' => $app['user_id'],
                'money' => $user['rate'],
                'app_path' => "",
                'udid' => $udid,
                'pid' => $user['pid'],
                'download' => "",
                'create_time' => date('Y-m-d H:i:s'),
                'is_overseas' => $is_overseas,
            ];
            Db::table($bale_rate_table)->insert($bale_rate);
            User::where("id",$user["id"])->dec("sign_num",1)->update();
            $plist = $this->get_plist($callback,$app["icon"],$app["package_name"],$app["name"],$udid);
            $plist = "https://".$domain['download_url'].'/'.$plist;
            return $this->getInstallAppXml($plist,$token);
        }

    }

    /**
     * 生成下载plist
     * @param $callback
     * @param $logo
     * @param $bundle
     * @param $name
     * @param $udid
     * @return string
     */
    public function get_plist($callback,$logo,$bundle,$name,$udid){
        $plist = <<<ETO
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>items</key>
	<array>
		<dict>
			<key>assets</key>
			<array>
				<dict>
					<key>kind</key>
					<string>software-package</string>
					<key>url</key>
					<string>$callback</string>
				</dict>
				<dict>
					<key>kind</key>
					<string>full-size-image</string>
					<key>needs-shine</key>
					<false/>
					<key>url</key>
					<string>$logo</string>
				</dict>
				<dict>
					<key>kind</key>
					<string>display-image</string>
					<key>needs-shine</key>
					<false/>
					<key>url</key>
					<string>$logo</string>
				</dict>
			</array>
			<key>metadata</key>
			<dict>
				<key>bundle-identifier</key>
				<string>$bundle</string>
				<key>bundle-version</key>
				<string>1</string>
				<key>kind</key>
				<string>software</string>
				<key>subtitle</key>
				<string></string>
				<key>title</key>
				<string>$name</string>
			</dict>
		</dict>
	</array>
</dict>
</plist>
ETO;
        $path = public_path()."cache/".date("Ymd")."/";
        if(!is_dir($path)){
            mkdir($path,0777,true);
        }
        file_put_contents($path.$udid.".plist",$plist);
        return "cache/".date("Ymd")."/".$udid.".plist";
    }

    public function getInstallAppXml($plist,$token){
        $app_xml=<<<ETO
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN""http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
    <key>Command</key>
    <dict>
        <key>RequestType</key>
        <string>InstallApplication</string> 
        <key>ManifestURL</key>
        <string>$plist</string>
        <key>ManagementFlags</key>
        <integer>1</integer>
    </dict>
    <key>CommandUUID</key>
    <string>$token</string>
</dict>
</plist>
ETO;
        return $app_xml;
    }


    /**
     * 获取设备型号
     * @param string $product
     * @return string
     */
    public static function getDevices($product = '')
    {
        switch ($product) {
            case "iPod5,1":
                return "iPod Touch 5";
            case "iPod7,1":
                return "iPod Touch 6";
            case "iPhone3,1":
            case "iPhone3,2":
            case "iPhone3,3" :
                return "iPhone4";
            case"iPhone4,1":
                return "iPhone4s";
            case"iPhone5,1" :
            case "iPhone5,2":
                return "iPhone5";
            case "iPhone5,3" :
            case "iPhone5,4":
                return "iPhone5c";
            case "iPhone6,1":
            case "iPhone6,2":
                return "iPhone5s";
            case"iPhone7,2":
                return "iPhone6";
            case"iPhone7,1":
                return "iPhone6 Plus";
            case"iPhone8,1":
                return "iPhone6s";
            case"iPhone8,2":
                return "iPhone6s Plus";
            case"iPhone8,4":
                return "iPhoneSE";
            case"iPhone9,1" :
            case "iPhone9,3":
                return "iPhone7";
            case "iPhone9,2" :
            case "iPhone9,4":
                return "iPhone7 Plus";
            case "iPhone10,1" :
            case "iPhone10,4":
                return "iPhone8";
            case "iPhone10,5" :
            case "iPhone10,2":
                return "iPhone8 Plus";
            case "iPhone10,3" :
            case "iPhone10,6":
                return "iPhoneX";
            case"iPhone11,2":
                return "iPhoneXS";
            case"iPhone11,6":
                return "iPhoneXS_MAX";
            case"iPhone11,8":
                return "iPhoneXR";
            case "iPhone12,1":
                return "iPhone11";
            case "iPhone12,3":
                return "iPhone11 Pro";
            case "iPhone12,5":
                return "iPhone11 Pro Max";
            case "iPad2,1" :
            case"iPad2,2":
            case "iPad2,3":
            case"iPad2,4":
                return "iPad 2";
            case "iPad3,1":
            case"iPad3,2" :
            case"iPad3,3":
                return "iPad 3";
            case "iPad3,4":
            case"iPad3,5" :
            case "iPad3,6":
                return "iPad 4";
            case "iPad4,1" :
            case "iPad4,2" :
            case "iPad4,3":
                return "iPad Air";
            case"iPad5,3" :
            case "iPad5,4":
                return "iPad Air 2";
            case "iPad2,5":
            case "iPad2,6" :
            case"iPad2,7":
                return "iPad Mini";
            case "iPad4,4":
            case"iPad4,5":
            case"iPad4,6":
                return "iPad Mini 2";
            case "iPad4,7":
            case"iPad4,8":
            case"iPad4,9":
                return "iPad Mini 3";
            case"iPad5,1":
            case"iPad5,2":
                return "iPad Mini 4";
            case"iPad6,7" :
            case "iPad6,8":
                return "iPad Pro";
            case"AppleTV5,3":
                return "Apple TV";
            case"i386" :
            case "x86_64":
                return "Simulator";
            default:
                return $product;
        }
    }


    /**
     * 获取加密描述文件
     * @param string $app_name
     * @param string $tag
     * @param string $callback
     * @param string $bundle
     * @param array $extend
     * @param array $oss_config
     * @param string $lang
     * @return bool|string
     */
    public function getMobileConfig($app_name = '', $tag = '', $callback = '', $bundle = 'dev.skyfox.profile-service',  $extend = [],$lang="zh")
    {
        $name = $tag . '.mobileconfig';
        $path = ROOT_PATH . '/runtime/openssl/' . uniqid() . '/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        exec("rm $path/*.mobileconfig");
        $cache_path = $path . $name;
        $out_name = $tag . '_sign.mobileconfig';
        $lang_list = [
            'zh'=>[
                1=>"授权安装进入下一步",
                2=>"点击安装",
                3=>"该配置文件帮助用户进行APP授权安装！",
            ],
            "tw"=>[
                1=>"授权安装进入下一步",
                2=>"点击安装",
                3=>"该配置文件帮助用户进行APP授权安装！",
            ],
            "en" => [
                1 => "Authorize installation to the next step",
                2 => "Click install",
                3 => "This configuration file helps users to authorize the installation of APP！",
            ],
            /**越南***/
            "vi" => [
                1 => "Cho phép cài đặt sang bước tiếp theo",
                2 => "Bấm cài đặt",
                3 => "Tệp cấu hình này giúp người dùng cho phép cài đặt APP！",
            ],
            /**印尼**/
            "id" => [
                1 => "Otorisasi penginstalan ke langkah berikutnya",
                2 => "Klik install",
                3 => "File konfigurasi ini membantu pengguna untuk mengotorisasi penginstalan APP！",
            ],
            /***泰语**/
            "th" => [
                1 => "อนุญาตการติดตั้งในขั้นตอนถัดไป",
                2 => "คลิกติดตั้ง",
                3 => "ไฟล์กำหนดค่านี้ช่วยให้ผู้ใช้อนุญาตการติดตั้ง APP！",
            ],
            /**韩语**/
            "ko" => [
                1 => "다음 단계로 설치 권한 부여",
                2 => "클릭 설치",
                3 => "이 구성 파일은 사용자가 APP 설치를 승인하는 데 도움이됩니다.！",
            ],
            /**日语**/
            "ja" => [
                1 => "次のステップへのインストールを承認します",
                2 => "[インストール]をクリックします",
                3 => "この構成ファイルは、ユーザーがAPPのインストールを承認するのに役立ちます！",
            ],
            "hi" => [
                1 => "अगला कदम स्थापना के अधिकार देना है",
                2 => "इंस्टॉल पर क्लिक करें",
                3 => "यह कॉन्फ़िगरेशन फ़ाइल उपयोगकर्ताओं को एपीपी की स्थापना को अधिकृत करने में मदद करती है!",
            ]
        ];
        if(array_key_exists($lang,$lang_list)){
            $lang_sub =$lang_list[$lang];
        }else{
            $lang_sub =$lang_list["zh"];
        }
        $a = $lang_sub[1];
        $b = $lang_sub[2];
        $c = $lang_sub[3];
        $str = <<<ETO
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
    <dict>
        <key>PayloadContent</key>
        <dict>
            <key>URL</key>
            <string>$callback</string>
            <key>DeviceAttributes</key>
            <array>
                <string>UDID</string>
                <string>IMEI</string>
                <string>ICCID</string>
                <string>VERSION</string>
                <string>PRODUCT</string>
            </array>
        </dict>
        <key>PayloadOrganization</key>
        <string>$a</string>
        <key>PayloadDisplayName</key>
        <string>$app_name --【$b 】</string>
        <key>PayloadVersion</key>
        <integer>1</integer>
        <key>PayloadUUID</key>
        <string>3C4DC7D2-E475-3375-489C-0BB8D737A653</string>
        <key>PayloadIdentifier</key>
        <string>$bundle</string>
        <key>PayloadDescription</key>
        <string>$c This configuration file helps users with APP license installation!</string>
        <key>PayloadType</key>
        <string>Profile Service</string>
    </dict>
</plist>
ETO;
        file_put_contents($cache_path, $str);
        $oss_config =OssConfig::where("name","g_oss")
            ->where("status",1)
            ->cache(true,10*60)
            ->find();
        $oss = new Oss($oss_config);
        if ($oss->ossDownload($extend['pem_path'], $path . 'ios.pem') &&
            $oss->ossDownload($extend['cert_path'], $path . 'ios.crt') &&
            $oss->ossDownload($extend['key_path'], $path . 'ios.key')) {

        } else {
            return false;
        }
        $shell = "cd $path && openssl smime -sign -in $name -out $out_name -signer ios.crt -inkey ios.key -certfile ios.pem -outform der -nodetach";
        exec($shell, $log, $status);
        if ($status == 0) {
            $cache_public_path = "cache/mobileconfig/".date("Ymd")."/";
            $out_path = public_path().$cache_public_path;
            if(!is_dir($out_path)){
                mkdir($out_path,0777,true);
            }
            copy($path . $out_name, $out_path.$out_name);
            $this->delDir($path);
            return "/".$cache_public_path.$out_name;
        } else {
            $this->delDir($path);
            return false;
        }
    }


    /***
     * 获取UDID
     * @param null $data
     * @return |null
     */
    public static function getUdid($data = null)
    {
        $plistBegin = '<?xml version="1.0"';
        $plistEnd = '</plist>';
        $pos1 = strpos($data, $plistBegin);
        $pos2 = strpos($data, $plistEnd);
        $data2 = substr($data, $pos1, $pos2 - $pos1);
        $xml = xml_parser_create();
        xml_parse_into_struct($xml, $data2, $vs);
        xml_parser_free($xml);
        $arrayCleaned = [];
        foreach ($vs as $v) {
            if ($v['level'] == 3 && $v['type'] == 'complete') {
                $arrayCleaned[] = $v;
            }
        }
        $result = null;
        foreach ($arrayCleaned as $k => $v) {
            if (isset($v['value']) && ($v['value'] == 'UDID' || $v['value'] == 'PRODUCT')) {
                $result[strtolower($v['value'])] = $arrayCleaned[$k + 1]['value'] ?? '';
            }
        }
        return $result;
    }

    /**
     * curl请求
     * @param string $url
     * @param null $data
     * @param array $header
     * @return bool|string
     */
    protected function http_request($url = '', $data = null, $header = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_HEADER, $header);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl,CURLOPT_TIMEOUT,180);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    /**
     * 查询已安装APP命令
     * @param $token
     */
    public static function searchAppListCommand($token){
        $app_xml=<<<ETO
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN""http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
    <key>Command</key>
    <dict>
        <key>RequestType</key>
        <string>ManagedApplicationList</string> 
    </dict>
    <key>CommandUUID</key>
    <string>$token</string>
</dict>
</plist>
ETO;
        return $app_xml;
    }


    public static function installAppListCommand($token){
        $app_xml=<<<ETO
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN""http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
    <key>Command</key>
    <dict>
        <key>RequestType</key>
        <string>InstalledApplicationList</string> 
    </dict>
    <key>CommandUUID</key>
    <string>$token</string>
</dict>
</plist>
ETO;
        return $app_xml;
    }

    public static function getDeviceInfo($token){
        $app_xml=<<<ETO
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN""http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
    <dict>
        <key>Command</key>
        <dict>
            <key>RequestType</key>
            <string>DeviceInformation</string>
            <key>Queries</key>
            <array>
                <string>ModelName</string>
                <string>Model</string>
                <string>BatteryLevel</string>
                <string>DeviceCapacity</string>
                <string>AvailableDeviceCapacity</string>
                <string>OSVersion</string>
                <string>ProductName</string>
                <string>IMEI</string>
            </array>
        </dict>
        <key>CommandUUID</key>
        <string>$token</string>
    </dict>
</plist>
ETO;
        return $app_xml;
    }

    /***
     * 增加一次扣费
     */
    public function add_pay_app($app_id = 0){
        $url = "http://35.241.123.37:85/api/sua_add_pay";
        $sign =md5($app_id."sign".date("Ymd"));
        $post = [
            "app_id"=>$app_id,
            "sign"=>$sign
        ];
        $result = $this->http_request($url,$post);
        $res = json_decode($result,true);
        if(isset($res["code"])&&$res["code"]==200){
            return true;
        }else{
            return false;
        }
    }


}