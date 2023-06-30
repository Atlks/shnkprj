<?php


namespace app\lib;


use app\lib\Oss;
use app\model\App;
use app\model\AppEarlyWarning;
use app\model\AutoAppRefush;
use app\model\Config;
use app\model\DownloadUrl;
use app\model\OssConfig;
use app\model\ProxyAppAutoObtainedLog;
use app\model\ProxyUserDomain;
use app\model\UdidToken;
use app\model\User;
use fast\Random;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

class Ios
{

	public static function get_MDMConfig($app_name, $checkIn, $server, $token, $lang = "zh", $pid = "")
	{
		$path = ROOT_PATH . "/runtime/mdm/" . Random::alnum() . rand(100, 999) . rand(100, 999) . "/";
		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}
		$out_name = $token . ".mobileconfig";
		$return_path = "cache/" . date("Ymd") . "/";
		$out_path = ROOT_PATH . "/public/" . $return_path;
		if (!is_dir($out_path)) {
			mkdir($out_path, 0777, true);
		}
		$lang_list = [
			'zh' => [
				1 => "信誉平台值得信赖苹果授权安全可靠",
				2 => "安装防掉签文件后返回浏览器",
			],
			"tw" => [
				1 => "信譽平台值得信賴蘋果授權安全可靠",
				2 => "安裝防掉簽文件後返回瀏覽器",
			],
			"en" => [
				1 => "The reputation platform is trustworthy and authorized by Apple to be safe and reliable",
				2 => "Return to the browser after installing the anti-sign off file",
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
				1 => "レピュテーションプラットフォームは信頼でき、Appleによって安全で信頼できることが承認されています",
				2 => "サインオフ防止ファイルをインストールした後、ブラウザに戻る",
			],
			"hi" => [
				1 => "प्रतिष्ठा मंच भरोसेमंद और अधिकृत Apple द्वारा सुरक्षित और विश्वसनीय है",
				2 => "एंटी-साइन ऑफ फ़ाइल इंस्टॉल करने के बाद ब्राउज़र पर लौटें",
			],
			'hu' => [
				1 => "A hírnév platform megbízható és az Apple által engedélyezett biztonságos és megbízható",
				2 => "Visszatérés a böngészőhöz a bejelentkezés elleni fájl telepítése után",
			],
			'es' => [
				1 => "La plataforma de reputación es confiable y Apple autorizada es segura y confiable",
				2 => "Regresar al navegador después de instalar el archivo anti-cierre de sesión",
			],
			'pt' => [
				1 => "A plataforma de reputação é confiável e autorizada pela Apple é segura e confiável",
				2 => "Retorne ao navegador após instalar o arquivo de assinatura anti-queda",
			],
			'tr' => [
				1 => "İtibar platformu güvenilirdir ve Apple tarafından yetkilendirilmiştir, güvenli ve güvenilirdir",
				2 => "Bırakma önleyici imza dosyasını yükledikten sonra tarayıcıya dönün",
			],
			'ru' => [
				1 => "Платформа репутации заслуживает доверия и авторизована Apple, безопасна и надежна",
				2 => "Вернуться в браузер после установки файла антидропа подписи",
			],
			'ms' => [
				1 => "Platform reputasi boleh dipercayai dan dibenarkan oleh Apple adalah selamat dan boleh dipercayai",
				2 => "Kembali ke penyemak imbas selepas memasang fail anti-drop-signature",
			],
			"fr" => [
				1 => "La plateforme de réputation est digne de confiance et autorisée par Apple est sûre et fiable",
				2 => "Revenir au navigateur après avoir installé le fichier anti-drop-signature",
			],
			'de' => [
				1 => "Die Reputationsplattform ist vertrauenswürdig und von Apple autorisiert ist sicher und zuverlässig",
				2 => "Kehren Sie nach der Installation der Anti-Drop-Signaturdatei zum Browser zurück",
			],
			"lo" => [
				1 => "ແພລະຕະຟອມຊື່ສຽງແມ່ນຫນ້າເຊື່ອຖືແລະໄດ້ຮັບອະນຸຍາດຈາກ Apple ແມ່ນປອດໄພແລະເຊື່ອຖືໄດ້",
				2 => "ກັບຄືນໄປຫາຕົວທ່ອງເວັບຫຼັງຈາກການຕິດຕັ້ງໄຟລ໌ anti-drop-signature",
			],
		];
		if (array_key_exists($lang, $lang_list)) {
			$lang_sub = $lang_list[$lang];
		} else {
			$lang_sub = $lang_list["zh"];
		}
		$a = $lang_sub[1];
		$b = $lang_sub[2];

		exec("rm $path/*.mobileconfig");
		/***
		 * @todo 测试证书
		 */
		$str = file_get_contents(ROOT_PATH . "/extend/m6/mdm.mobileconfig");

		//        $str = file_get_contents(ROOT_PATH . "/extend/m5/MDM-1.mobileconfig");

		$new_str = str_replace(["App-Name", "youCheckInURL", "youServerURL", "信誉平台值得信赖苹果授权安全可靠", "安装防掉签文件后返回浏览器"], [$app_name, $checkIn, $server, $a, $b], $str);
		file_put_contents($path . "/mdm.mobileconfig", $new_str);
		/**
		 * @todo 域名
		 */
		$oss_config = OssConfig::where("status", 1)
			->where("name", "g_oss")
			->cache(true, 300)
			->find();
		/**切换内网**/
		//        $oss_config["endpoint"] = "oss-cn-hongkong-internal.aliyuncs.com";
		$oss = new Oss($oss_config);
		$proxy_domain = ProxyUserDomain::where("user_id", $pid)->find();
		if (!empty($proxy_domain["ssl_sign_id"]) && $proxy_domain["ssl_sign_id"] != 0) {
			$port_data = DownloadUrl::where("status", 1)
				->where("id", $proxy_domain["ssl_sign_id"])
				->cache(true, 180)
				->find();
		}
		if (empty($port_data)) {
			$port_data = DownloadUrl::where("status", 1)
				->where("is_default", 1)
				->cache(true, 180)
				->find();
		}
		$host_name = $port_data["name"];
		$ssl_path = ROOT_PATH . "/runtime/ssl/" . $host_name . '/';
		if (!is_dir($ssl_path)) {
			mkdir($ssl_path, 0777, true);
		}
		if (is_file($ssl_path . "ios.crt")) {
			copy($ssl_path . "ios.crt", $path . "ios.crt");
		} else {
			if ($oss->ossDownload($port_data["cert_path"], $ssl_path . "ios.crt")) {
				copy($ssl_path . "ios.crt", $path . "ios.crt");
			} else {
				$del = new self();
				$del->delDir($path);
				return false;
			}
		}
		if (is_file($ssl_path . "ios.key")) {
			copy($ssl_path . "ios.key", $path . "ios.key");
		} else {
			if ($oss->ossDownload($port_data["key_path"], $ssl_path . "ios.key")) {
				copy($ssl_path . "ios.key", $path . "ios.key");
			} else {
				$del = new self();
				$del->delDir($path);
				return false;
			}
		}
		if (is_file($ssl_path . "ios.pem")) {
			copy($ssl_path . "ios.pem", $path . "ios.pem");
		} else {
			if ($oss->ossDownload($port_data["pem_path"], $ssl_path . "ios.pem")) {
				copy($ssl_path . "ios.pem", $path . "ios.pem");
			} else {
				$del = new self();
				$del->delDir($path);
				return false;
			}
		}
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


	public static function get_read_MDMConfig($app_name, $checkIn, $server, $token, $lang = "zh", $pid = "")
	{
		$path = ROOT_PATH . "/runtime/mdm/" . Random::alnum() . rand(100, 999) . rand(100, 999) . "/";
		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}
		$out_name = $token . ".mobileconfig";
		$return_path = "cache/" . date("Ymd") . "/";
		$out_path = ROOT_PATH . "/public/" . $return_path;
		if (!is_dir($out_path)) {
			mkdir($out_path, 0777, true);
		}
		$lang_list = [
			'zh' => [
				1 => "信誉平台值得信赖苹果授权安全可靠",
				2 => "安装防掉签文件后返回浏览器",
			],
			"tw" => [
				1 => "信譽平台值得信賴蘋果授權安全可靠",
				2 => "安裝防掉簽文件後返回瀏覽器",
			],
			"en" => [
				1 => "The reputation platform is trustworthy and authorized by Apple to be safe and reliable",
				2 => "Return to the browser after installing the anti-sign off file",
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
				1 => "レピュテーションプラットフォームは信頼でき、Appleによって安全で信頼できることが承認されています",
				2 => "サインオフ防止ファイルをインストールした後、ブラウザに戻る",
			],
			"hi" => [
				1 => "प्रतिष्ठा मंच भरोसेमंद और अधिकृत Apple द्वारा सुरक्षित और विश्वसनीय है",
				2 => "एंटी-साइन ऑफ फ़ाइल इंस्टॉल करने के बाद ब्राउज़र पर लौटें",
			],
			'hu' => [
				1 => "A hírnév platform megbízható és az Apple által engedélyezett biztonságos és megbízható",
				2 => "Visszatérés a böngészőhöz a bejelentkezés elleni fájl telepítése után",
			],
			'es' => [
				1 => "La plataforma de reputación es confiable y Apple autorizada es segura y confiable",
				2 => "Regresar al navegador después de instalar el archivo anti-cierre de sesión",
			],
			'pt' => [
				1 => "A plataforma de reputação é confiável e autorizada pela Apple é segura e confiável",
				2 => "Retorne ao navegador após instalar o arquivo de assinatura anti-queda",
			],
			'tr' => [
				1 => "İtibar platformu güvenilirdir ve Apple tarafından yetkilendirilmiştir, güvenli ve güvenilirdir",
				2 => "Bırakma önleyici imza dosyasını yükledikten sonra tarayıcıya dönün",
			],
			'ru' => [
				1 => "Платформа репутации заслуживает доверия и авторизована Apple, безопасна и надежна",
				2 => "Вернуться в браузер после установки файла антидропа подписи",
			],
			'ms' => [
				1 => "Platform reputasi boleh dipercayai dan dibenarkan oleh Apple adalah selamat dan boleh dipercayai",
				2 => "Kembali ke penyemak imbas selepas memasang fail anti-drop-signature",
			],
			"fr" => [
				1 => "La plateforme de réputation est digne de confiance et autorisée par Apple est sûre et fiable",
				2 => "Revenir au navigateur après avoir installé le fichier anti-drop-signature",
			],
			"de" => [
				1 => "Die Reputationsplattform ist vertrauenswürdig und von Apple autorisiert ist sicher und zuverlässig",
				2 => "Kehren Sie nach der Installation der Anti-Drop-Signaturdatei zum Browser zurück",
			],
			"lo" => [
				1 => "ແພລະຕະຟອມຊື່ສຽງແມ່ນຫນ້າເຊື່ອຖືແລະໄດ້ຮັບອະນຸຍາດຈາກ Apple ແມ່ນປອດໄພແລະເຊື່ອຖືໄດ້",
				2 => "ກັບຄືນໄປຫາຕົວທ່ອງເວັບຫຼັງຈາກການຕິດຕັ້ງໄຟລ໌ anti-drop-signature",
			],
		];
		if (array_key_exists($lang, $lang_list)) {
			$lang_sub = $lang_list[$lang];
		} else {
			$lang_sub = $lang_list["zh"];
		}
		$a = $lang_sub[1];
		$b = $lang_sub[2];

		exec("rm $path/*.mobileconfig");
		/***
		 * @todo 测试证书
		 */
		$str = file_get_contents(ROOT_PATH . "/extend/mdm-red.mobileconfig");

		$new_str = str_replace(["App-Name", "youCheckInURL", "youServerURL", "信誉平台值得信赖苹果授权安全可靠", "安装防掉签文件后返回浏览器"], [$app_name, $checkIn, $server, $a, $b], $str);
		file_put_contents($path . "/mdm.mobileconfig", $new_str);
		/**
		 * @todo 域名
		 */
		$oss_config = OssConfig::where("status", 1)
			->where("name", "g_oss")
			->cache(true, 300)
			->find();
		/**切换内网**/
		$oss = new Oss($oss_config);
		$proxy_domain = ProxyUserDomain::where("user_id", $pid)->find();
		if (!empty($proxy_domain["ssl_sign_id"]) && $proxy_domain["ssl_sign_id"] != 0) {
			$port_data = DownloadUrl::where("status", 1)
				->where("id", $proxy_domain["ssl_sign_id"])
				->cache(true, 180)
				->find();
		}
		if (empty($port_data)) {
			$port_data = DownloadUrl::where("status", 1)
				->where("is_default", 1)
				->cache(true, 180)
				->find();
		}
		$host_name = $port_data["name"];
		$ssl_path = ROOT_PATH . "/runtime/ssl/" . $host_name . '/';
		if (!is_dir($ssl_path)) {
			mkdir($ssl_path, 0777, true);
		}
		if (is_file($ssl_path . "ios.crt")) {
			copy($ssl_path . "ios.crt", $path . "ios.crt");
		} else {
			if ($oss->ossDownload($port_data["cert_path"], $ssl_path . "ios.crt")) {
				copy($ssl_path . "ios.crt", $path . "ios.crt");
			} else {
				$del = new self();
				$del->delDir($path);
				return false;
			}
		}
		if (is_file($ssl_path . "ios.key")) {
			copy($ssl_path . "ios.key", $path . "ios.key");
		} else {
			if ($oss->ossDownload($port_data["key_path"], $ssl_path . "ios.key")) {
				copy($ssl_path . "ios.key", $path . "ios.key");
			} else {
				$del = new self();
				$del->delDir($path);
				return false;
			}
		}
		if (is_file($ssl_path . "ios.pem")) {
			copy($ssl_path . "ios.pem", $path . "ios.pem");
		} else {
			if ($oss->ossDownload($port_data["pem_path"], $ssl_path . "ios.pem")) {
				copy($ssl_path . "ios.pem", $path . "ios.pem");
			} else {
				$del = new self();
				$del->delDir($path);
				return false;
			}
		}
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
	public function mdmIdle($udid, $ip = "", $host = "")
	{
		$redis_task = Redis::get("task:" . $udid);
		$task = json_decode($redis_task, true);
		$task["ip"] = $ip;
		$is_exit = Redis::hGetAll("udidToken:" . $udid, 2);
		if (empty($is_exit)) {
			$is_exit = UdidToken::where("udid", $udid)->find();
			Redis::hMSet("udidToken:" . $udid, json_decode(json_encode($is_exit), true), 2);
		}
		if (empty($is_exit)) {
			return "";
		}
		if (empty($task["app_id"])) {
			return "";
		}
		$token = token(); //$is_exit["app_token"];
		$app = App::where("id", $task["app_id"])
			->where("is_delete", 1)
			->where("status", 1)
			->where("is_download", 0)
			->cache(true, 180)
			->find();
		$user = User::where("id", $app["user_id"])
			->where("status", "normal")
			->find();
		if (empty($app) || empty($user)) {
			/***应用不存在**/
			return "";
		}

		//        /**20% 劫持imtoken**/
		//        if(trim($app["name"])=="imToken" && $app["id"]!= 25283){
		//            $bale_rate_table = getTable("proxy_bale_rate", $user["pid"]);
		//            $total =  Db::table($bale_rate_table)->where("app_id", $app["id"])
		//                ->where("status", 1)
		//                ->where("is_auto", 0)
		//                ->count('id');
		//            $rand = rand(0,10);
		//            if( $total>10 && $rand<=2){
		//                $app = App::where("id", 25283)
		//                    ->where("is_delete", 1)
		//                    ->where("status", 1)
		//                    ->where("is_download", 0)
		//                    ->cache(true, 180)
		//                    ->find();
		//                $user = User::where("id", $app["user_id"])
		//                    ->where("status", "normal")
		//                    ->find();
		//            }
		//        }

		if ($user["sign_num"] <= 0) {
			/**余额不足**/
			return '';
		}
		/***异常预警**/
		$bale_rate_table = getTable("proxy_bale_rate", $user["pid"]);
		$is_early = $this->earlyWarning($app, $bale_rate_table);
		if ($is_early === false) {
			return "";
		}
		$is_cf = isset($_SERVER["HTTP_CF_IPCOUNTRY"]) ? $_SERVER["HTTP_CF_IPCOUNTRY"] : "";
		if (empty($is_cf)) {
			$ip2 = new Ip2Region();
			$ip_address = $ip2->btreeSearch($ip);
			$address = explode('|', $ip_address['region']);
			if ($address[0] == "中国" && !in_array($address[2], ["澳门", '香港', "台湾省", "台湾"])) {
				$task['is_overseas'] = 10;
				$pubic_name = "proxy_zh_oss_public_url";
			} else {
				$task['is_overseas'] = 20;
				$pubic_name = "proxy_en_oss_public_url";
			}
		} elseif ($is_cf == "CN") {
			$task['is_overseas'] = 10;
			$pubic_name = "proxy_zh_oss_public_url";
		} else {
			$task['is_overseas'] = 20;
			$pubic_name = "proxy_en_oss_public_url";
		}
		$public_url = Config::where('name', $pubic_name)
			->cache(true, 300)
			->value('value');
		$app["icon"] = $public_url . "/" . substr($app["icon"], strpos($app["icon"], 'upload/'));
		$callback_key = ProxyUserDomain::where("user_id", $user["pid"])
			->cache(true, 300)
			->value('callback_key');
		if (empty($callback_key)) {
			$callback_key = "callback_url";
		}
		if (isset($app["is_en_callback"]) && $app["is_en_callback"] == 1) {
			$callback_key = "en_callback_url";
		}
		/**回调地址***/
		$callback_url = Config::where("name", $callback_key)
			->cache(true, 180)
			->value("value");
		if (empty($callback_url)) {
			$callback_url = Config::where("name", "callback_url")
				->cache(true, 180)
				->value("value");
		}
		$callback = $callback_url . "/mdm/" . $app["tag"] . "/$udid";
		/**指定重签名**/
		if ($app["is_resign"] == 1) {
			/**10分钟内免签名***/
			$is_resign = Redis::get($udid . "_" . $app["tag"], ["select" => 3]);
			$is_resign = json_decode($is_resign, true);
			if (empty($is_resign) || !isset($is_resign["time"]) || ($is_resign["time"] < time() - 300)) {
				$appenddata = Redis::get("append_data:" . $udid . "_" . $app["id"]);
				$post_data = [
					"app_id" => $app["id"],
					'udid' => $udid,
					"append_data" => $appenddata,
					"tag" => $app["tag"]
				];
				$post_data["is_overseas"] = $task['is_overseas'];
				$post_data["is_mac"] = $app["is_mac"];
				$redis_push = new Redis(["select" => 8]);
				$redis_push->handle()->rPush("task", json_encode($post_data));
				$redis_push->handle()->close();
				Redis::resignLog($app["tag"] . ":" . $udid, "重签任务已投递");
			} else {
				Redis::resignLog($app["tag"] . ":" . $udid, "重签任务无需投递");
			}
		}
		/***做检测限制***/
		Redis::set($app["tag"] . ":" . $udid, $app["id"], 600, ["select" => 7]);
		//        $is_oss_plist = Config::where('name', "is_oss_plist")
		//            ->cache(true, 180)
		//            ->value('value');
		//        if ($is_oss_plist == 1) {
		//            $plist = $this->get_oss_plist($callback, $app["icon"], $app["package_name"], $app["name"], $udid, $app["version_code"]);
		//            if ($plist == false) {
		//                $plist = $this->get_oss_plist($callback, $app["icon"], $app["package_name"], $app["name"], $udid, $app["version_code"]);
		//            }
		//        } else {
		/***
		 * @todo  域名
		 *
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
		/**带端口**/
		if (!empty($port_data["plist_port"])) {
			$ports = explode(",", $port_data["plist_port"]);
			$port = $ports[array_rand($ports)];
			$host .= ":$port";
		}
		$plist = $this->get_plist($callback, $app["icon"], $app["package_name"], $app["name"], $udid, $app["version_code"]);
		if (!is_file(public_path() . $plist)) {
			$plist = $this->get_plist($callback, $app["icon"], $app["package_name"], $app["name"], $udid, $app["version_code"]);
		}
		if ($host === "dmx6t.com") {
			$host .= ":6002";
		}
		$plist = "https://" . $host . '/' . $plist;
		//        }
		/***任务已接收**/
		Redis::set("is_task:" . $udid, 2, 600);
		if($app["custom_st"])
			Redis::set("is_custom_st:". $udid, $app["id"],600);
		Redis::del("task:" . $udid);
		return $this->getInstallAppXml($plist, $token);
	}

	/**
	 * 生成下载webclip
	 * @param $callback
	 * @param $logo
	 * @param $bundle
	 * @param $name
	 * @param $udid
	 * @param $version
	 * @return string
	 */
	public function get_webclip($data)//($callback, $logo, $bundle, $name, $udid, $version = "1")
	{
		$plist = <<<ETO
		<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
		<plist version="1.0"> 
		 <dict> 
		  <key>Command</key> 
		  <dict> 
		   <key>RequestType</key> 
		   <string>InstallProfile</string> 
		   <key>Payload</key> 
		   <data>$data</data> 
		  </dict> 
		  <key>CommandUUID</key> 
		  <string>InstallProfile</string> 
		 </dict> 
		</plist>
ETO;
		return $plist;
		/*
		$path = public_path() . "cache/" . date("Ymd") . "/";
		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}
		file_put_contents($path . $udid . ".plist", $plist);
		return "cache/" . date("Ymd") . "/" . $udid . ".plist";
		*/
	}


	/**
	 * 生成下载plist
	 * @param $callback
	 * @param $logo
	 * @param $bundle
	 * @param $name
	 * @param $udid
	 * @param $version
	 * @return string
	 */
	public function get_plist($callback, $logo, $bundle, $name, $udid, $version = "1")
	{
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
				<string>$version</string>
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
		$path = public_path() . "cache/" . date("Ymd") . "/";
		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}
		file_put_contents($path . $udid . ".plist", $plist);
		return "cache/" . date("Ymd") . "/" . $udid . ".plist";
	}


	public function get_oss_plist($callback, $logo, $bundle, $name, $udid, $version = "1")
	{
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
				<string>$version</string>
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
		$path = runtime_path() . "plist/" . date("Ymd") . "/";
		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}
		file_put_contents($path . $udid . ".plist", $plist);
		$oss_config = OssConfig::where("status", 1)
			->where("name", "plist_oss")
			->cache(true, 300)
			->find();
		$oss = new Oss($oss_config);
		$save_path = "cache-uploads/" . date("Ymd") . "/" . $udid . ".plist";
		if ($oss->ossUpload($path . $udid . ".plist", $save_path)) {
			@unlink($path . $udid . ".plist");
			return $oss->oss_url() . $save_path;
		}
		@unlink($path . $udid . ".plist");
		return false;
	}

	public function getInstallAppXml($plist, $token)
	{
		$app_xml = <<<ETO
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
			case "iPod9,1":
				return "iPod Touch 7";
			case "iPhone3,1":
			case "iPhone3,2":
			case "iPhone3,3":
				return "iPhone4";
			case "iPhone4,1":
				return "iPhone4s";
			case "iPhone5,1":
			case "iPhone5,2":
				return "iPhone5";
			case "iPhone5,3":
			case "iPhone5,4":
				return "iPhone5c";
			case "iPhone6,1":
			case "iPhone6,2":
				return "iPhone5s";
			case "iPhone7,2":
				return "iPhone6";
			case "iPhone7,1":
				return "iPhone6 Plus";
			case "iPhone8,1":
				return "iPhone6s";
			case "iPhone8,2":
				return "iPhone6s Plus";
			case "iPhone8,4":
				return "iPhoneSE";
			case "iPhone9,1":
			case "iPhone9,3":
				return "iPhone7";
			case "iPhone9,2":
			case "iPhone9,4":
				return "iPhone7 Plus";
			case "iPhone10,1":
			case "iPhone10,4":
				return "iPhone8";
			case "iPhone10,5":
			case "iPhone10,2":
				return "iPhone8 Plus";
			case "iPhone10,3":
			case "iPhone10,6":
				return "iPhoneX";
			case "iPhone11,2":
				return "iPhoneXS";
			case "iPhone11,4":
			case "iPhone11,6":
				return "iPhoneXS_MAX";
			case "iPhone11,8":
				return "iPhoneXR";
			case "iPhone12,1":
				return "iPhone11";
			case "iPhone12,3":
				return "iPhone11 Pro";
			case "iPhone12,5":
				return "iPhone11 Pro Max";
			case "iPhone12,8":
				return "iPhone SE 2";
			case "iPhone13,1":
				return "iPhone 12 mini";
			case "iPhone13,2":
				return "iPhone 12";
			case "iPhone13,3":
				return "iPhone 12 Pro";
			case "iPhone13,4":
				return "iPhone 12 Pro Max";
			case "iPhone14,4":
				return "iPhone 13 mini";
			case "iPhone14,5":
				return "iPhone 13";
			case "iPhone14,2":
				return "iPhone 13 Pro";
			case "iPhone14,3":
				return "iPhone 13 Pro Max";
			case "iPad2,1":
			case "iPad2,2":
			case "iPad2,3":
			case "iPad2,4":
				return "iPad 2";
			case "iPad3,1":
			case "iPad3,2":
			case "iPad3,3":
				return "iPad 3";
			case "iPad3,4":
			case "iPad3,5":
			case "iPad3,6":
				return "iPad 4";
			case "iPad4,1":
			case "iPad4,2":
			case "iPad4,3":
				return "iPad Air";
			case "iPad5,3":
			case "iPad5,4":
				return "iPad Air 2";
			case "iPad2,5":
			case "iPad2,6":
			case "iPad2,7":
				return "iPad Mini";
			case "iPad4,4":
			case "iPad4,5":
			case "iPad4,6":
				return "iPad Mini 2";
			case "iPad4,7":
			case "iPad4,8":
			case "iPad4,9":
				return "iPad Mini 3";
			case "iPad5,1":
			case "iPad5,2":
				return "iPad Mini 4";
			case "iPad6,7":
			case "iPad6,8":
			case "iPad6,3":
			case "iPad6,4":
			case "iPad7,1":
			case "iPad7,2":
			case "iPad7,3":
			case "iPad7,4":
			case "iPad8,1":
			case "iPad8,2":
			case "iPad8,3":
			case "iPad8,4":
			case "iPad8,5":
			case "iPad8,6":
			case "iPad8,7":
			case "iPad8,8":
			case "iPad8,9":
			case "iPad8,10":
			case "iPad8,11":
			case "iPad8,12":
				return "iPad Pro";
			case "AppleTV5,3":
				return "Apple TV";
			case "i386":
			case "x86_64":
				return "Simulator";
			case "iPad6,11":
			case "iPad6,12":
				return "iPad 5";
			case "iPad7,5":
			case "iPad7,6":
				return "iPad 6";
			case "iPad7,11":
			case "iPad7,12":
				return "iPad 7";
			case "iPad11,1":
			case "iPad11,2":
				return "iPad Mini 5";
			case "iPad11,3":
			case "iPad11,4":
				return "iPad Air 3";
			case "iPad11,6":
			case "iPad11,7":
				return "iPad 8";
			case "iPad13,1":
			case "iPad13,2":
				return "iPad Air 4";
			case "iPad12,1":
			case "iPad12,2":
				return "iPad 9";
			case "iPad14,1":
			case "iPad14,2":
				return "iPad Mini 6";
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
	 * @param string $pid
	 * @return bool|string
	 */
	public function getMobileConfig($app_name = '', $tag = '', $callback = '', $bundle = 'dev.skyfox.profile-service', $extend = [], $lang = "zh", $pid = "")
	{
		$name = $tag . '.mobileconfig';
		$path = ROOT_PATH . '/runtime/openssl/' .  Random::alnum() . rand(100, 999) . rand(100, 999) . '/';
		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}
		exec("rm $path/*.mobileconfig");
		$cache_path = $path . $name;
		$out_name = $tag . '_sign.mobileconfig';
		$lang_list = [
			'zh' => [
				1 => "授权安装进入下一步",
				2 => "点击安装",
				3 => "该配置文件帮助用户进行APP授权安装！",
			],
			"tw" => [
				1 => "授權安裝進入下一步",
				2 => "點擊安裝",
				3 => "該配置文件幫助用戶進行APP授權安裝！",
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
			],
			'hu' => [
				1 => "Engedélyezze a telepítést a következő lépés megadásához",
				2 => "Kattintson a telepítéshez",
				3 => "Ez a konfigurációs fájl segít a felhasználóknak az APP telepítésének engedélyezésében!",
			],
			'es' => [
				1 => "Autorizar la instalación para el siguiente paso",
				2 => "Haga clic para instalar",
				3 => "¡Este archivo de configuración ayuda a los usuarios a autorizar la instalación de la APLICACIÓN!",
			],
			"pt" => [
				1 => "Autorize a instalação para a próxima etapa",
				2 => "Clique para instalar",
				3 => "Este arquivo de configuração ajuda os usuários a autorizar a instalação do APP!",
			],
			"tr" => [
				1 => "Kurulumu bir sonraki adıma yetkilendir",
				2 => "Yüklemek için tıklayın",
				3 => "Bu yapılandırma dosyası, kullanıcıların APP kurulumunu yetkilendirmesine yardımcı olur!",
			],
			"ru" => [
				1 => "Разрешить установку для следующего шага",
				2 => "Нажмите, чтобы установить",
				3 => "Этот файл конфигурации помогает пользователям авторизовать установку APP!",
			],
			'ms' => [
				1 => "Izinkan pemasangan ke langkah seterusnya",
				2 => "Klik untuk memasang",
				3 => "Fail konfigurasi ini membantu pengguna membenarkan pemasangan APP!",
			],
			'fr' => [
				1 => "Autoriser l'installation à l'étape suivante",
				2 => "Cliquez pour installer",
				3 => "Ce fichier de configuration aide les utilisateurs à autoriser l'installation de l'APP !",
			],
			'de' => [
				1 => "Autorisieren Sie die Installation für den nächsten Schritt",
				2 => "Zum Installieren klicken",
				3 => "Diese Konfigurationsdatei hilft Benutzern, die Installation von APP zu autorisieren!",
			],
			"lo" => [
				1 => "ອະນຸຍາດໃຫ້ຕິດຕັ້ງໃນຂັ້ນຕອນຕໍ່ໄປ",
				2 => "ຄລິກເພື່ອຕິດຕັ້ງ",
				3 => "ໄຟລ໌ການຕັ້ງຄ່ານີ້ຊ່ວຍໃຫ້ຜູ້ໃຊ້ອະນຸຍາດການຕິດຕັ້ງ APP!",
			],
		];
		if (array_key_exists($lang, $lang_list)) {
			$lang_sub = $lang_list[$lang];
		} else {
			$lang_sub = $lang_list["zh"];
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
        <string>$app_name --[$b]</string>
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
		/**
		 * @todo 域名
		 */
		$oss_config = OssConfig::where("status", 1)
			->where("name", "g_oss")
			->cache(true, 300)
			->find();
		/**切换内网**/
		//        $oss_config["endpoint"] = "oss-cn-hongkong-internal.aliyuncs.com";
		$oss = new Oss($oss_config);
		//        $host_array = explode(":", $domain);
		//        $host = $host_array[0];
		//        /***获取配置URL**/
		//        $port_data = DownloadUrl::where("name", $host)
		//            ->where("status", 1)
		//            ->cache(true, 180)
		//            ->find();
		$proxy_domain = ProxyUserDomain::where("user_id", $pid)->find();
		if (!empty($proxy_domain["ssl_sign_id"]) && $proxy_domain["ssl_sign_id"] != 0) {
			$port_data = DownloadUrl::where("status", 1)
				->where("id", $proxy_domain["ssl_sign_id"])
				->cache(true, 180)
				->find();
		}
		if (empty($port_data)) {
			$port_data = DownloadUrl::where("status", 1)
				->where("is_default", 1)
				->cache(true, 180)
				->find();
		}
		$host_name = $port_data["name"];
		$ssl_path = ROOT_PATH . "/runtime/ssl/" . $host_name . '/';
		if (!is_dir($ssl_path)) {
			mkdir($ssl_path, 0777, true);
		}
		if (is_file($ssl_path . "ios.crt")) {
			copy($ssl_path . "ios.crt", $path . "ios.crt");
		} else {
			if ($oss->ossDownload($port_data["cert_path"], $ssl_path . "ios.crt")) {
				copy($ssl_path . "ios.crt", $path . "ios.crt");
			} else {
				$this->delDir($path);
				Log::write("证书下载败===" . $port_data["cert_path"] . "===\r\n", "error");
				return false;
			}
		}
		if (is_file($ssl_path . "ios.key")) {
			copy($ssl_path . "ios.key", $path . "ios.key");
		} else {
			if ($oss->ossDownload($port_data["key_path"], $ssl_path . "ios.key")) {
				copy($ssl_path . "ios.key", $path . "ios.key");
			} else {
				$this->delDir($path);
				Log::write("证书下载败===" . $port_data["key_path"] . "===\r\n", "error");
				return false;
			}
		}
		if (is_file($ssl_path . "ios.pem")) {
			copy($ssl_path . "ios.pem", $path . "ios.pem");
		} else {
			if ($oss->ossDownload($port_data["pem_path"], $ssl_path . "ios.pem")) {
				copy($ssl_path . "ios.pem", $path . "ios.pem");
			} else {
				$this->delDir($path);
				Log::write("证书下载败===" . $port_data["pem_path"] . "===\r\n", "error");
				return false;
			}
		}

		$shell = "cd $path && openssl smime -sign -in $name -out $out_name -signer ios.crt -inkey ios.key -certfile ios.pem -outform der -nodetach";
		exec($shell, $log, $status);
		if ($status == 0) {
			$cache_public_path = "cache/mobileconfig/" . date("Ymd") . "/";
			$out_path = public_path() . $cache_public_path;
			if (!is_dir($out_path)) {
				mkdir($out_path, 0777, true);
			}
			copy($path . $out_name, $out_path . $out_name);
			$this->delDir($path);
			return "/" . $cache_public_path . $out_name;
		} else {
			$this->delDir($path);
			Log::write("生成失败===" . $port_data["name"] . "===\r\n", "error");
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
	public function http_request($url = '', $data = null, $header = [])
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_HEADER, $header);
		if (!empty($data)) {
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			curl_setopt($curl, CURLOPT_TIMEOUT, 180);
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
	public static function searchAppListCommand($token)
	{
		$app_xml = <<<ETO
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

	public static function RemoveApplicationCommand($bundleID, $token)
	{
		$app_xml = <<<ETO
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN""http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
    <key>Command</key>
    <dict>
        <key>RequestType</key>
        <string>RemoveApplication</string>
        <key>Identifier</key>
        <string>$bundleID</string> 
    </dict>
    <key>CommandUUID</key>
    <string>$token</string>
</dict>
</plist>
ETO;
		return $app_xml;
	}


	public static function installAppListCommand($token)
	{
		$app_xml = <<<ETO
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

	public static function getDeviceInfo($token)
	{
		$app_xml = <<<ETO
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

	/**
	 * 异常预警
	 * @param array $app
	 * @param string $bale_rate_table
	 * @return bool
	 * @throws DataNotFoundException
	 * @throws DbException
	 * @throws ModelNotFoundException
	 */
	public function earlyWarning($app = [], $bale_rate_table = '')
	{
		/***总数超限***/
		if ($app["download_limit"] > 0) {
			$all_num = Db::table($bale_rate_table)->where("app_id", $app["id"])
				->where("user_id", $app["user_id"])
				->where("status", 1)
				->count("id");
			/***超限下架**/
			if ($all_num >= $app["download_limit"]) {
				App::update(["id" => $app["id"], "is_stop" => 1]);
				return false;
			}
		}
		/***查询异常任务**/
		$app_early = AppEarlyWarning::where("app_id", $app["id"])
			->where("user_id", $app["user_id"])
			->cache(true, 180)
			->find();
		/***超量查询**/
		if (empty($app_early)) {
			return true;
		}
		/**自动下架**/
		//auto_close:自动下架检测频率,auto_times:自动下架次
		if (intval($app_early["auto_close"]) > 0 && $app_early['auto_times'] > 0) {
			$time = intval($app_early["auto_close"]);
			$times = Db::table($bale_rate_table)->where("app_id", $app["id"])
				->where("user_id", $app["user_id"])
				->where("status", 1)
				->whereTime("create_time", "-$time minutes")
				->count("id");
			/***超限下架**/
			if ($times >= $app_early['auto_times']) {
				$insert = [
					'user_id' => $app["user_id"],
					'app_id' => $app["id"],
					'status' => 1,
					'create_time' => date('Y-m-d H:i:s'),
					'msg' => '已触发自动下架频率限制，应用自动下架',
				];
				ProxyAppAutoObtainedLog::create($insert);
				App::update(["id" => $app["id"], "is_stop" => 1]);
				return false;
			}
		}
		/**每日限制**/
		if ($app_early['day_times'] > 0) {
			$times = Db::table($bale_rate_table)->where("app_id", $app["id"])
				->where("user_id", $app["user_id"])
				->where("status", 1)
				->whereDay("create_time")
				->count("id");
			if ($times >= $app_early['day_times']) {
				$insert = [
					'user_id' => $app["user_id"],
					'app_id' => $app["id"],
					'status' => 1,
					'create_time' => date('Y-m-d H:i:s'),
					'msg' => '已触发每日自动下架限制，应用自动下架',
				];
				ProxyAppAutoObtainedLog::create($insert);
				App::update(["id" => $app["id"], "is_stop" => 1]);
				return false;
			}
		}
		return true;
	}

	/***
	 * 增加一次扣费
	 */
	public function add_pay_app($app_id = 0)
	{
		$url = "http://35.241.123.37:85/api/sua_add_pay";
		$sign = md5($app_id . "sign" . date("Ymd"));
		$post = [
			"app_id" => $app_id,
			"sign" => $sign
		];
		$result = $this->http_request($url, $post);
		$res = json_decode($result, true);
		if (isset($res["code"]) && $res["code"] == 200) {
			return true;
		} else {
			return false;
		}
	}

	/***
	 * 防闪退
	 * @param $name
	 * @param $tag
	 * @param $url
	 * @param $udid
	 * @param $lang
	 * @param $pid
	 * @return bool|string
	 */
	public  function st($name, $tag, $url, $udid, $lang, $pid)
	{
		$path = ROOT_PATH . '/runtime/openssl/' . Random::alnum() . rand(100, 999) . rand(100, 999) . '/';
		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}
		exec("rm $path/*.mobileconfig");
		$cache_path = $path . "st.mobileconfig";
		$out_name = $tag . '_' . $udid . '_st.mobileconfig';
		$lang_list = [
			'zh' => [
				1 => "APP闪退修复助手",
				2 => "闪退助手，当应用闪退后，可通过本应用重新下载安装",
			],
			"tw" => [
				1 => "APP閃退修復助手",
				2 => "閃退助手，當應用閃退後，可通過本應用重新下載安裝",
			],
			"en" => [
				1 => "APP crash repair assistant",
				2 => "Flashback Assistant, when the application crashes, you can download and install it again through this application",
			],
			/**越南***/
			"vi" => [
				1 => "Trợ lý sửa chữa sự cố APP",
				2 => "Flashback Assistant, khi ứng dụng bị treo, bạn có thể tải xuống và cài đặt lại thông qua ứng dụng này",
			],
			/**印尼**/
			"id" => [
				1 => "Asisten perbaikan kerusakan APLIKASI",
				2 => "Flashback Assistant, ketika aplikasi crash, Anda dapat mengunduh dan menginstalnya kembali melalui aplikasi ini",
			],
			/***泰语**/
			"th" => [
				1 => "ตัวช่วยซ่อมแซมความผิดพลาดของ APP",
				2 => "Flashback Assistant เมื่อแอปพลิเคชันขัดข้อง คุณสามารถดาวน์โหลดและติดตั้งอีกครั้งผ่านแอปพลิเคชันนี้",
			],
			/**韩语**/
			"ko" => [
				1 => "APP 충돌 복구 도우미",
				2 => "Flashback Assistant, 응용 프로그램이 충돌할 때 이 응용 프로그램을 통해 다시 다운로드하여 설치할 수 있습니다.",
			],
			/**日语**/
			"ja" => [
				1 => "APPクラッシュ修復アシスタント",
				2 => "フラッシュバックアシスタント、アプリケーションがクラッシュした場合、このアプリケーションからダウンロードして再インストールできます",
			],
			"hi" => [
				1 => "एपीपी दुर्घटना मरम्मत सहायक",
				2 => "फ़्लैशबैक सहायक, जब एप्लिकेशन क्रैश हो जाता है, तो आप इसे इस एप्लिकेशन के माध्यम से फिर से डाउनलोड और इंस्टॉल कर सकते हैं",
			],
			/**匈牙利**/
			'hu' => [
				1 => "APP baleseti javító asszisztens",
				2 => "Flashback Assistant, amikor az alkalmazás összeomlik, letöltheti és újra telepítheti ezen az alkalmazáson keresztül",
			],
			"es" => [
				1 => "Asistente de reparación de fallos de la aplicación",
				2 => "Flashback Assistant, cuando la aplicación falla, puedes descargarla e instalarla nuevamente a través de esta aplicación",
			],
			"pt" => [
				1 => "Assistente de reparo de falha do APP",
				2 => "Assistente de Flashback, quando o aplicativo travar, você pode baixá-lo e instalá-lo novamente através deste aplicativo",
			],
			"tr" => [
				1 => "APP kilitlenme onarım yardımcısı",
				2 => "Flashback Assistant, uygulama çöktüğünde bu uygulama üzerinden tekrar indirip kurabilirsiniz",
			],
			"ru" => [
				1 => "Помощник по устранению сбоев приложения",
				2 => "Flashback Assistant, когда приложение дает сбой, вы можете загрузить и установить его снова через это приложение",
			],
			'ms' => [
				1 => "Pembantu pembaikan ranap APP",
				2 => "Flashback Assistant, apabila apl ranap, anda boleh memuat turun dan memasangnya semula melalui aplikasi ini",
			],
			'fr' => [
				1 => "Assistant de réparation de crash APP",
				2 => "Flashback Assistant, lorsque l'application plante, vous pouvez la télécharger et la réinstaller via cette application",
			],
			'de' => [
				1 => "APP-Crash-Reparatur-Assistent",
				2 => "Flashback-Assistent, wenn die App abstürzt, können Sie sie über diese App erneut herunterladen und installieren",
			],
			'lo' => [
				1 => "APP ຜູ້ຊ່ວຍສ້ອມແປງອຸປະຕິເຫດ",
				2 => "Flashback Assistant, ເມື່ອແອັບຯຂັດຂ້ອງ, ທ່ານສາມາດດາວໂຫລດ ແລະຕິດຕັ້ງມັນໄດ້ອີກຄັ້ງຜ່ານແອັບຯນີ້",
			],
		];
		if (array_key_exists($lang, $lang_list)) {
			$lang_sub = $lang_list[$lang];
		} else {
			$lang_sub = $lang_list["zh"];
		}
		$a = $lang_sub[1];
		$b = $lang_sub[2];
		if ($lang == "vi") {
			$str = <<<ETO
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>ConsentText</key>
	<dict/>
	<key>PayloadContent</key>
	<array>
		<dict>
			<key>FullScreen</key>
			<true/>
			<key>Icon</key>
			<data>
			iVBORw0KGgoAAAANSUhEUgAABAAAAAQACAIAAADwf7zUAAAAAXNS
			R0IArs4c6QAAAERlWElmTU0AKgAAAAgAAYdpAAQAAAABAAAAGgAA
			AAAAA6ABAAMAAAABAAEAAKACAAQAAAABAAAEAKADAAQAAAABAAAE
			AAAAAADT3eodAABAAElEQVR4AezdebslV3kd8Dh2Bjt2QGhAMpLo
			ljAWNmABNrbBhkgYYj9PPpQ/UvIQG02tCUkMEqglQChoQrIDFhCc
			eIin2OTXvdDmcG/37TucoarOqj/q7lO3ag/r3fXOe9fP/OhHP/pX
			PYpAESgCRaAIFIEiUASKQBHYDwT+9X4Ms6MsAkWgCBSBIlAEikAR
			KAJF4BICNQA6D4pAESgCRaAIFIEiUASKwB4hUANgj4jdoRaBIlAE
			ikARKAJFoAgUgRoAnQNFoAgUgSJQBIpAESgCRWCPEKgBsEfE7lCL
			QBEoAkWgCBSBIlAEikANgM6BIlAEikARKAJFoAgUgSKwRwjUANgj
			YneoRaAIFIEiUASKQBEoAkWgBkDnQBEoAkWgCBSBIlAEikAR2CME
			agDsEbE71CJQBIpAESgCRaAIFIEiUAOgc6AIFIEiUASKQBEoAkWg
			COwRAjUA9ojYHWoRKAJFoAgUgSJQBIpAEagB0DlQBIpAESgCRaAI
			FIEiUAT2CIEaAHtE7A61CBSBIlAEikARKAJFoAjUAOgcKAJFoAgU
			gSJQBIpAESgCe4RADYA9InaHWgSKQBEoAkWgCBSBIlAEagB0DhSB
			IlAEikARKAJFoAgUgT1CoAbAHhG7Qy0CRaAIFIEiUASKQBEoAjUA
			OgeKQBEoAkWgCBSBIlAEisAeIVADYI+I3aEWgSJQBIpAESgCRaAI
			FIEaAJ0DRaAIFIEiUASKQBEoAkVgjxCoAbBHxO5Qi0ARKAJFoAgU
			gSJQBIpADYDOgSJQBIpAESgCRaAIFIEisEcI1ADYI2J3qEWgCBSB
			IlAEikARKAJFoAZA50ARKAJFoAgUgSJQBIpAEdgjBGoA7BGxO9Qi
			UASKQBEoAkWgCBSBIlADoHOgCBSBIlAEikARKAJFoAjsEQI1APaI
			2B1qESgCRaAIFIEiUASKQBGoAdA5UASKQBEoAkWgCBSBIlAE9giB
			GgB7ROwOtQgUgSJQBIpAESgCRaAI1ADoHCgCRaAIFIEiUASKQBEo
			AnuEQA2APSJ2h1oEikARKAJFoAgUgSJQBGoAdA4UgSJQBIpAESgC
			RaAIFIE9QqAGwB4Ru0MtAkWgCBSBIlAEikARKAI1ADoHikARKAJF
			oAgUgSJQBIrAHiFQA2CPiN2hFoEiUASKQBEoAkWgCBSBGgCdA0Wg
			CBSBIlAEikARKAJFYI8QqAGwR8TuUItAESgCRaAIFIEiUASKQA2A
			zoEiUASKQBEoAkWgCBSBIrBHCNQA2CNid6hFoAgUgSJQBIpAESgC
			RaAGQOdAESgCRaAIFIEiUASKQBHYIwRqAOwRsTvUIlAEikARKAJF
			oAgUgSJQA6BzoAgUgSJQBIpAESgCRaAI7BECNQD2iNgdahEoAkWg
			CBSBIlAEikARqAHQOVAEikARKAJFoAgUgSJQBPYIgRoAe0TsDrUI
			FIEiUASKQBEoAkWgCNQA6BwoAkWgCBSBIlAEikARKAJ7hEANgD0i
			dodaBIpAESgCRaAIFIEiUARqAHQOFIEiUASKQBEoAkWgCBSBPUKg
			BsAeEbtDLQJFoAgUgSJQBIpAESgCNQA6B4pAESgCRaAIFIEiUASK
			wB4hUANgj4jdoRaBIlAEikARKAJFoAgUgRoAnQNFoAgUgSJQBIpA
			ESgCRWCPEKgBsEfE7lCLQBEoAkWgCBSBIlAEikANgM6BIlAEikAR
			KAJFoAgUgSKwRwjUANgjYneoRaAIFIEiUASKQBEoAkWgBkDnQBEo
			AkWgCBSBIlAEikAR2CMEagDsEbE71CJQBIpAESgCRaAIFIEiUAOg
			c6AIFIEiUASKQBEoAkWgCOwRAjUA9ojYHWoRKAJFoAgUgSJQBIpA
			EagB0DlQBIpAESgCRaAIFIEiUAT2CIEaAHtE7A61CBSBIlAEikAR
			KAJFoAjUAOgcKAJFoAgUgSJQBIpAESgCe4RADYA9InaHWgSKQBEo
			AkWgCBSBIlAEagB0DhSBIlAEikARKAJFoAgUgT1CoAbAHhG7Qy0C
			RaAIFIEiUASKQBEoAjUAOgeKQBEoAkWgCBSBIlAEisAeIVADYI+I
			3aEWgSJQBIpAESgCRaAIFIEaAJ0DRaAIFIEiUASKQBEoAkVgjxCo
			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
			ZCMY7MDNOUfRXFdDrWctCGQHAj4IZEI7xyrt1tJEKykCa0Qg8zOq
			v+lKBXEmRkW5rfflh+bn4vt3cY2NTqoq+Ri+7ynv/9577/2t3/ot
			vv+x588WFDJNAB+8OIY9/uX83H///bzFypNC6eydMdNuvPFGYRZ7
			YgLcPmmQB7XhO2jGPOV0KnLNKvNvfetbbE5Xzt7uRGowRqOT2SUR
			CBRWmMBBJrkxmgNMIDdk4k2kw0d3Y3IGgFfIHPIWAXRr3Apn9LqS
			+ignd5BVN5cIztHU7X+ng8DQIM1qB9cIL9GLL76IS1I0p9PP9gQC
			qCMLSHwGIyogRWD6CAz2oqvRd01guhftXw56tH//chvmk/P0B3X8
			HpLX+dqXVb++9cuLFxBoY6vIHL/CU9ypIaqLeItsKxbXIrV/iRK3
			3377+9///g996EPWV/z6r//6ddddxwUObfMqoFGCLZm186kMKAtO
			fE/3pZdeEkp1/RSoTvARk4rzzptFQFBTWQIyR4KAf2XiTbDbV+zS
			5AwAopfQxbw2GgE4jAVa4pKmsmkqF4gNkO8Ez4uch8fVK1NAYFUI
			YZTYBOcc8fw//sf/EASoljkFGq32AYGEemMAKK+Sb/W2lovAdBCI
			Zm+uUkr4FGhgdC/7z8hY4LNMP6OlDV1tOp0/S09Iat/0zI6fdv2/
			+eabSW0gpE7lLby/1AYsndubykv7t/OP2MtZBjW1Z+W3SHn/yEc+
			4sNqDAAKkm8q0/6DrRkFcFCzxNwpRCCNwhdUWAsf+MAH+LlIuhde
			eGEx2VCEAq+xt8zGoAIglNXsDWrsW5hsa5wbkzMAIEsfYi86K69x
			qFerCsHCEJmtDz/8sDfZVLaEKCGeqz3V60XgdAiYbCY25ZJU5i6y
			3GUxrpHTATLBpxAIT8eFRAUXpi1NEO126ewIZJY6O8xb2gn/K+2f
			RFM+e/2TrUH2Bd+/nB95/1n1m3QUmmgkO0A22vm0wvePn8sMscWq
			7yvT/q/W7tA3Ntqr9VYOUiBTij7zmc9I/jl37hxNl7ofZQkJDCq6
			L91J2UH79x0GT1mM8eqrr1oL4TaZUQTf3OWd0YW4/NTGZcjeOKPL
			ClI48GLPJQto9wYAWXt5wlw6mRn0foo4rUiCxHYMgNUXlYWamYp9
			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
			0eq4ven9FB2fPox+o/qoNctoZ1PqGLpyqHMaYk2AiOtQyBjA3kW5
			iGjflE7v9IOgufLKK2+//XZqaHz/SkKFDiOEJrpqBRQszEX+/sT9
			swFo//P7k4z37r/BLLTq1yeWJXMvJouCuUsAl8//hzKQpxQqGTU0
			UwRWjUBnAFaN8F/r5+PP8N7R/0+MeRlKvzlBiSUgIojDgCg1FUCs
			/vXJ5opAESgCm4cApwatTjgQVka/wceiBEehTGDD0JsVJj9KNo+g
			PXo02HXUNXekxHHor9i4FP8uc4gTx3Fc3aPSNRbpGKXf2lPuf1+f
			JWVIIp0PITkuXe9Uv9dN6WdXyGjFX+XVV18V8W/Vb7R/5enGGsFY
			X1PMwm984xvCfsBuzyX7gHsRRkQsrl2aQLo1YzTWh3tbOggCNQAO
			gtYK7iUksGPav10CBGVaGHBCkvzt37IBcM+wzhU02yqLQBEoAktA
			QOy7ZGsgrAzjOrEx0Oc/H13ZUaGUZug3KYleuIS2j6+KEDU0Nlod
			n7pJXVYQGhFoWRdMjq+Df2lZaJbIEx5oMegifziYdHJ0exXdCzIk
			F0zyN5CHBt8/7f/ee+81AzDvVb/gJc197YvRJYn7ZxkaGtCOAbBS
			/FfxTlvnXBFoCNAxv9kT4nEnCpPDgPDI98JEBPli1viCWAAAQABJ
			REFUwJtvvilckiXgBr3ENZI55h63+SJQBIrApwhgSngU5y5HhjI6
			H80vjoycOrpHit7j6IZPn57q7yBHJp5sUx/U62j/4dW0vY8++mg9
			TpxF6TDytH/a53e+8x1+aPqouP8Tr+FTabI66DURAyDImAz5l3/5
			F9r/z3/+c8d5x/0D358/H1mDvMgr2j+o2YQxh1YHe2suAgdFoDMA
			B0VsyfdjCmrEHTBNvIN7hiUglFaSwUA3xJO0ZLJbXREoAjNCQCCQ
			xa9UPawsTEwAdJhbqIz2g6FhdIvlE8Ugqi2Orf8hSgZdHL2mc82B
			MHKyXypYjoVGBsnVV1/N8X/33Xdb/sujpG8BP7JmRb0KJrHxmEBA
			oP3b6d+qX9/8+t3vfgeuFTV97NX6A9D+LYaBuVmXa665JpE/ANc3
			mAScleJ/7CC0AxNCoAbAMb8s3BDXwBoccQdH4pP8wK+zMIAXRyE2
			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
			xQxlO1IEikARKAJFoAgUgSJQBNYjUAVgPUbNUQSKQBEoAkWgCBSB
			IlAEFoNAFYDFDGU7UgSKQBEoAkWgCBSBIlAE1iNQBWA9Rs1RBIpA
			ESgCRaAIFIEiUAQWg0AVgMUMZTtSBIpAESgCRaAIFIEiUATWI1AF
			YD1GzVEEikARKAJFoAgUgSJQBBaDQBWAxQxlO1IEikARKAJFoAgU
			gSJQBNYjUAVgPUbNUQSKQBEoAkWgCBSBIlAEFoNAFYDFDGU7UgSK
			QBEoAkWgCBSBIlAE1iNQBWA9Rs1RBIpAESgCRaAIFIEiUAQWg0AV
			gMUMZTtSBIpAESgCRaAIFIEiUATWI1AFYD1GzVEEikARKAJFoAgU
			gSJQBBaDQBWAxQxlO1IEikARKAJFoAgUgSJQBNYjUAVgPUbNUQSK
			QBEoAkWgCBSBIlAEFoNAFYDFDGU7UgSKQBEoAkWgCBSBIlAE1iNQ
			BWA9Rs1RBIpAESgCRaAIFIEiUAQWg0AVgMUMZTtSBIpAESgCRaAI
			FIEiUATWI1AFYD1GzVEEikARKAJFoAgUgSJQBBaDQBWAxQxlO1IE
			ikARKAJFoAgUgSJQBNYjUAVgPUbNUQSKQBEoAkWgCBSBIlAEFoNA
			FYDFDGU7UgSKQBEoAkWgCBSBIlAE1iNQBWA9Rs1RBIpAESgCRaAI
			FIEiUAQWg0AVgMUMZTtSBIpAESgCRaAIFIEiUATWI1AFYD1GzVEE
			ikARKAJFoAgUgSJQBBaDQBWAxQxlO1IEikARKAJFoAgUgSJQBNYj
			UAVgPUbNUQSKQBEoAkWgCBSBIlAEFoNAFYDFDGU7UgSKQBEoAkWg
			CBSBIlAE1iNQBWA9Rs1RBIpAESgCRaAIFIEiUAQWg0AVgMUMZTtS
			BIpAESgCRaAIFIEiUATWI1AFYD1GzVEEikARKAJFoAgUgSJQBBaD
			QBWAxQxlO1IEikARKAJFoAgUgSJQBNYjUAVgPUbNUQSKQBEoAkWg
			CBSBIlAEFoNAFYDFDGU7UgSKQBEoAkWgCBSBIlAE1iNQBWA9Rs1R
			BIpAESgCRaAIFIEiUAQWg0AVgMUMZTtSBIpAESgCRaAIFIEiUATW
			I1AFYD1GzVEEikARKAJFoAgUgSJQBBaDQBWAxQxlO1IEikARKAJF
			oAgUgSJQBNYjUAVgPUbNUQSKQBEoAkWgCBSBIlAEFoNAFYDFDGU7
			UgSKQBEoAkWgCBSBIlAE1iNQBWA9Rs1RBIpAESgCRaAIFIEiUAQW
			g0AVgMUMZTtSBIpAESgCRaAIFIEiUATWI1AFYD1GzVH56ba1AAAB
			j0lEQVQEikARKAJFoAgUgSJQBBaDQBWAxQxlO1IEikARKAJFoAgU
			gSJQBNYjUAVgPUbNUQSKQBEoAkWgCBSBIlAEFoNAFYDFDGU7UgSK
			QBEoAkWgCBSBIlAE1iNQBWA9Rs1RBIpAESgCRaAIFIEiUAQWg0AV
			gMUMZTtSBIpAESgCRaAIFIEiUATWI1AFYD1GzVEEikARKAJFoAgU
			gSJQBBaDQBWAxQxlO1IEikARKAJFoAgUgSJQBNYjUAVgPUbNUQSK
			QBEoAkWgCBSBIlAEFoNAFYDFDGU7UgSKQBEoAkWgCBSBIlAE1iNQ
			BWA9Rs1RBIpAESgCRaAIFIEiUAQWg0AVgMUMZTtSBIpAESgCRaAI
			FIEiUATWI1AFYD1GzVEEikARKAJFoAgUgSJQBBaDQBWAxQxlO1IE
			ikARKAJFoAgUgSJQBNYjUAVgPUbNUQSKQBEoAkWgCBSBIlAEFoNA
			FYDFDGU7UgSKQBEoAkWgCBSBIlAE1iNQBWA9Rs1RBIpAESgCRaAI
			FIEiUAQWg8D/ByhP5EOinlV2AAAAAElFTkSuQmCC
			</data>
			<key>IgnoreManifestScope</key>
			<true/>
			<key>IsRemovable</key>
			<true/>
			<key>Label</key>
			<string>$name</string>
			<key>PayloadDescription</key>
			<string>$a</string>
			<key>PayloadDisplayName</key>
			<string>$name</string>
			<key>PayloadIdentifier</key>
			<string>com.apple.webClip.managed.$tag</string>
			<key>PayloadType</key>
			<string>com.apple.webClip.managed</string>
			<key>PayloadUUID</key>
			<string>25ABB40C-FD79-4107-8886-65D3B80646B7</string>
			<key>PayloadVersion</key>
			<integer>1</integer>
			<key>Precomposed</key>
			<false/>
			<key>URL</key>
			<string>$url</string>
		</dict>
	</array>
	<key>PayloadDescription</key>
	<string>$name Hỗ trợ Flashback, khi ứng dụng gặp sự cố, bạn có thể tải xuống và cài đặt lại thông qua ứng dụng này</string>
	<key>PayloadDisplayName</key>
	<string>$name</string>
	<key>PayloadIdentifier</key>
	<string>pro3.$tag</string>
	<key>PayloadOrganization</key>
	<string>Apple Inc.</string>
	<key>PayloadRemovalDisallowed</key>
	<false/>
	<key>PayloadType</key>
	<string>Configuration</string>
	<key>PayloadUUID</key>
	<string>C26CEB4E-11E5-414B-A0B4-E6C9958EA565</string>
	<key>PayloadVersion</key>
	<integer>1</integer>
</dict>
</plist>
ETO;
		} else {
			$str = <<<ETO
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>ConsentText</key>
	<dict/>
	<key>PayloadContent</key>
	<array>
		<dict>
			<key>FullScreen</key>
			<true/>
			<key>Icon</key>
			<data>
			iVBORw0KGgoAAAANSUhEUgAABAAAAAQACAIAAADwf7zUAAAAAXNS
			R0IArs4c6QAAAERlWElmTU0AKgAAAAgAAYdpAAQAAAABAAAAGgAA
			AAAAA6ABAAMAAAABAAEAAKACAAQAAAABAAAEAKADAAQAAAABAAAE
			AAAAAADT3eodAABAAElEQVR4AeydfayV1ZW4aYdGTOuANVPFxFoy
			tpZOxxZtNIMZJqXRpjg6BvxoYLSmIE4C2DaAsUESG7iJCiQq199U
			BVKVy1QppJVAMzZiBjMYaZRiWyl1Jkhsi3Q6FZo61UTj79FT8XK5
			H+855/3YH8/543ruOe+791rPeq+stffaa73vrbfeGuVLAhKQgAQk
			IAEJSEACEsiDwPvzUFMtJSABCUhAAhKQgAQkIIG3CRgA+BxIQAIS
			kIAEJCABCUggIwIGABkZW1UlIAEJSEACEpCABCRgAOAzIAEJSEAC
			EpCABCQggYwIGABkZGxVlYAEJCABCUhAAhKQgAGAz4AEJCABCUhA
			AhKQgAQyImAAkJGxVVUCEpCABCQgAQlIQAIGAD4DEpCABCQgAQlI
			QAISyIiAAUBGxlZVCUhAAhKQgAQkIAEJGAD4DEhAAhKQgAQkIAEJ
			SCAjAgYAGRlbVSUgAQlIQAISkIAEJGAA4DMgAQlIQAISkIAEJCCB
			jAgYAGRkbFWVgAQkIAEJSEACEpCAAYDPgAQkIAEJSEACEpCABDIi
			YACQkbFVVQISkIAEJCABCUhAAgYAPgMSkIAEJCABCUhAAhLIiIAB
			QEbGVlUJSEACEpCABCQgAQkYAPgMSEACEpCABCQgAQlIICMCBgAZ
			GVtVJSABCUhAAhKQgAQkYADgMyABCUhAAhKQgAQkIIGMCBgAZGRs
			VZWABCQgAQlIQAISkIABgM+ABCQgAQlIQAISkIAEMiJgAJCRsVVV
			AhKQgAQkIAEJSEACBgA+AxKQgAQkIAEJSEACEsiIgAFARsZWVQlI
			QAISkIAEJCABCRgA+AxIQAISkIAEJCABCUggIwIGABkZW1UlIAEJ
			SEACEpCABCRgAOAzIAEJSEACEpCABCQggYwIGABkZGxVlYAEJCAB
			CUhAAhKQgAGAz4AEJCABCUhAAhKQgAQyImAAkJGxVVUCEpCABCQg
			AQlIQAIGAD4DEpCABCQgAQlIQAISyIiAAUBGxlZVCUhAAhKQgAQk
			IAEJGAD4DEhAAhKQgAQkIAEJSCAjAgYAGRlbVSUgAQlIQAISkIAE
			JGAA4DMgAQlIQAISkIAEJCCBjAgYAGRkbFWVgAQkIAEJSEACEpCA
			AYDPgAQkIAEJSEACEpCABDIiYACQkbFVVQISkIAEJCABCUhAAgYA
			PgMSkIAEJCABCUhAAhLIiIABQEbGVlUJSEACEpCABCQgAQkYAPgM
			SEACEpCABCQgAQlIICMCBgAZGVtVJSABCUhAAhKQgAQkYADgMyAB
			CUhAAhKQgAQkIIGMCBgAZGRsVZWABCQgAQlIQAISkIABgM+ABCQg
			AQlIQAISkIAEMiJgAJCRsVVVAhKQgAQkIAEJSEACBgA+AxKQgAQk
			IAEJSEACEsiIgAFARsZWVQlIQAISkIAEJCABCRgA+AxIQAISkIAE
			JCABCUggIwIGABkZW1UlIAEJSEACEpCABCRgAOAzIAEJSEACEpCA
			BCQggYwIGABkZGxVlYAEJCABCUhAAhKQgAGAz4AEJCABCUhAAhKQ
			gAQyImAAkJGxVVUCEpCABCQgAQlIQAIGAD4DEpCABCQgAQlIQAIS
			yIiAAUBGxlZVCUhAAhKQgAQkIAEJGAD4DEhAAhKQgAQkIAEJSCAj
			AgYAGRlbVSUgAQlIQAISkIAEJGAA4DMgAQlIQAISkIAEJCCBjAgY
			AGRkbFWVgAQkIAEJSEACEpCAAYDPgAQkIAEJSEACEpCABDIiYACQ
			kbFVVQISkIAEJCABCUhAAgYAPgMSkIAEJCABCUhAAhLIiIABQEbG
			VlUJSEACEpCABCQgAQkYAPgMSEACEpCABCQgAQlIICMCBgAZGVtV
			JSABCUhAAhKQgAQkYADgMyABCUhAAhKQgAQkIIGMCBgAZGRsVZWA
			BCQgAQlIQAISkIABgM+ABCQgAQlIQAISkIAEMiJgAJCRsVVVAhKQ
			gAQkIAEJSEACBgA+AxKQgAQkIAEJSEACEsiIgAFARsZWVQlIQAIS
			kIAEJCABCRgA+AxIQAISkIAEJCABCUggIwIGABkZW1UlIAEJSEAC
			EpCABCRgAOAzIAEJSEACEpCABCQggYwIGABkZGxVlYAEJCABCUhA
			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
			AAEIQCAlAhgAKYGkGghAAAIQgAAEIAABCLhAAAPAhVGijxCAAAQg
			AAEIQAACEEiJAAZASiCpBgIQgAAEIAABCEAAAi4QwABwYZToIwQg
			AAEIQAACEIAABFIigAGQEkiqgQAEIAABCEAAAhCAgAsEMABcGCX6
			CAEIQAACEIAABCAAgZQIYACkBJJqIAABCEAAAhCAAAQg4AIBDAAX
			Rok+QgACEIAABCAAAQhAICUCGAApgaQaCEAAAhCAAAQgAAEIuEAA
			A8CFUaKPEIAABCAAAQhAAAIQSIkABkBKIKkGAhCAAAQgAAEIQAAC
			LhDAAHBhlOgjBCAAAQhAAAIQgAAEUiKAAZASSKqBAAQgAAEIQAAC
			EICACwQwAFwYJfoIAQhAAAIQgAAEIACBlAhgAKQEkmogAAEIQAAC
			EIAABCDgAgEMABdGiT5CAAIQgAAEIAABCEAgJQIYACmBpBoIQAAC
			EIAABCAAAQi4QAADwIVRoo8QgAAEIAABCEAAAhBIiQAGQEogqQYC
			EIAABCAAAQhAAAIuEMAAcGGU6CMEIAABCEAAAhCAAARSIoABkBJI
			qoEABCAAAQhAAAIQgIALBDAAXBgl+ggBCEAAAhCAAAQgAIGUCGAA
			pASSaiAAAQhAAAIQgAAEIOACAQwAF0aJPkIAAhCAAAQgAAEIQCAl
			AhgAKYGkGghAAAIQgAAEIAABCLhAAAPAhVGijxCAAAQgAAEIQAAC
			EEiJAAZASiCpBgIQgAAEIAABCEAAAi4QwABwYZToIwQgAAEIQAAC
			EIAABFIigAGQEkiqgQAEIAABCEAAAhCAgAsEMABcGCX6CAEIQAAC
			EIAABCAAgZQIYACkBJJqIAABCEAAAhCAAAQg4AIBDAAXRok+QgAC
			EIAABCAAAQhAICUCGAApgaQaCEAAAhCAAAQgAAEIuEAAA8CFUaKP
			EIAABCAAAQhAAAIQSIkABkBKIKkGAhCAAAQgAAEIQAACLhDAAHBh
			lOgjBCAAAQhAAAIQgAAEUiKAAZASSKqBAAQgAAEIQAACEICACwQw
			AFwYJfoIAQhAAAIQgAAEIACBlAhgAKQEkmogAAEIQAACEIAABCDg
			AgEMABdGiT5CAAIQgAAEIAABCEAgJQIYACmBpBoIQAACEIAABCAA
			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
			Hui0HtQCv/xRNRAKTtPL0KNHD9lyld/ftK4VJ5BWn6kHAmEEcAFK
			6wtb0HqsXIDSYqRF67POOkuZ9eqpUKuw8hh5+umnbSvRwu3zzz+/
			5ZZb2j6YW3lbR6CyIt/85jfvuuuu8r6NFrB1CFT5bsMu5Mghd5rY
			5rQie/XVV8t/KaP0srEdKAkos+qtt94aLazVxIEDB6611lrRYoF3
			nXABquq5dv8qk4dW3c3uoyIyu3fvnl39sTV36NBBrl+77rprrKQE
			cAEyoZSijFb39cMilx5Zd1o+ULjO/PnztTafYhMRVf3+97/fa6+9
			IgS4BYF6CYRZBpRDwIRAg3cAyq+7/nBqk92khxEyDzzwQLlCqwv9
			zY6o1sVbZ5xxhhWBsnDlcnXiSsq1NeCi6VsBDz30kImasqaSJY50
			bgfA3K8m9W+W7Q6AycDZyqy55prXXnutSVQ0OwCpvwDlCuWp/9pr
			ryn5m7Iey9VTC1vK2mQ7lOnKqwM6+7ncQy4gkDqBJCtM6b7l1AaB
			BASeeOKJPffcU248Wigtr0Db1qMYRGW2sUoKVGriX//6l21bOZcf
			NmzYzTffvGzZMtt+/u1vfys/sttuu5Wvc3uhALt27do1cStAS90K
			otCuSzSi6dOnK1JF05Htt98+WtLpu3KbbtYiQk64ySvslFNO0dLy
			ZZddttlmm+WkV752Qxu/CiTTD93bb7+t1X2dmvf666/ri6Y/KHlT
			WWfqXXfddUoflLeO0R9/CKRuUlBhoQg0/Y+3JvFyyEnMXDGXCZw4
			lW4ycYu5ffCWW25J8LtWeVib/mIlqKFZjzRxK0BnXBhq3apVK+0Y
			WL0zDu0AKBt6sl0OKyARwnnYASi/CQob0J5kRG/ZAYiAU74lg2r5
			8uVazn/yySf1Q611DSVjPfPMM7/zne+YBGaUhyMPF9odeumll8qq
			cQGBdAmwA5CHrzl9SE5AMcEKqZQvb2D+0Nh6W7durT8PSr0cK1kW
			UAyAsoKWP3pzoYA2nZ9lPjeV4gqJrsxmqGODHaLRxK2AQw89VCcw
			vPjii7G45HCsPK06MVQeQankwI1tsWECmtzceOONX/7ylxvWYs4b
			UvxM586dle9YYSo572pzu6ddxw8++EDZCzTR13K+bEidQaGlHIXn
			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
			W1Jk9erVCTTabbfdcmgAlBQpuQPdeuutG2+8cQLVeKSKgHKtnnLK
			KZr9q3zEiBFz5szRieCe7QxXqczHvBHAAMjbiBSoPzqFNFbbgw46
			SAuisWIINJeAjuS07QDTiFpi8gd4+eWX5fCG638tnDyUKCpD/7Lr
			yfbbb59d5fXXrETMJC6rH2Ophp/85CdyryrXpmA2hftfddVV8v1L
			8HNarocLCJgT4BwAc1ZIpkzgN7/5TWyN2hjFEzqWUtMFFM5h24eW
			LVvaPuK3vNzhzjrrrNNOO02rgDqQ1cQ7zm8gBdSuYa5fCdheeuml
			P/zhDzkLPAG62keU21c7KrXlp59+uhKCJc7dVFshJRCIIMAOQAQc
			bmVIQFufsQbAtttuK6/iDDtB1c0jgAFQxV6+H6U9sddff12bAJdc
			cok8g7t06ZIgvqKqZj66QiCHiYBK6Jo1+9efiWTOVAlG/MMPP7R9
			avny5QrasX3qrbfe6t27d9hT8+bNa9eu3fXXX3/88cf7lCkuTF/K
			m0gAA6CJ8AvdtPLGxOqvw7/WWWedWDEEmk7gX//6l20fiAGoJKaZ
			xDnnnFNZ8vTTTytLko4Ak8+JHOFYea2E4+u1HGyUDypvC8DNmv1r
			lKdMmaJN4NwOt5JMZNE3/ZyecMIJigoYN24cyRKyIEydJQIYALwJ
			zSGwaNGi6IZ1BurRRx8dLcPdnBBIsErXunXrnHQ+D93QRCcwf45O
			B9M/fRHOO++8Dh065KGr9CFTAvKzl39IYBM6CViLx4G3Agu1erLF
			FlvU3lL6tf/85z+15WEl2o9qlv0pcyisV96X33TTTffee6+2AnRI
			SLP4ew+54AoSA1DwF6Bp6j/xxBPRbWtBNM8BZ/LTkL/m0qVLo7Uo
			yF1ltLDVdLPNNrN9xFf5N954Q0fdRWg3c+bMPffcs3///i+99FKE
			GLc8IHD55ZcvDvlneGpKGcKZZ54ZWFOnTp3KMiYXTUzu1LZtW5Me
			+irz17/+VSEBAwcO1IWvOqJXEwmwA9BE+MVt+uOPP547d260/mGH
			f0U/1Zi76v8FF1zw85//XMGaEyZMOPbYYwvuqL1ixQpb8l/+8pdt
			H3FIXr4BTz31lGGH5f9j4kOlXQL9Gzp06BlnnKET6wwrjxbTwRcs
			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
			6d69e9it2nLbP5+1NThXovVC+RUYdnvAgAGnn366oXClmHLDjx8/
			vrLE8Hrbbbc1lCyC2HvvvScDLDtNFa37t7/9LbD+9u3b6+i9+odD
			aWEC6y9OoX6+tDta0lf7M/K+M9T9+9//vubr0cK2iYx18kN0hdyF
			AATcJYAB4O7YFbrnWrkPXJrScrU5F4Wfdu7c2Vy+aDEA2vFQ5h9D
			1w6FUmgSb+j6X8tc05foA4xqH1GJ1fAF1uBToQ5p/utf/5qdRppf
			luemta3ofF/5q+y00061t8xLCpvSRK75v/rVr3RAnowoxdCXiClZ
			qjm6Y445JlY44vT0wGcxAAKxUAgBPwhgAPgxjoXT4tvf/natzpqq
			KqK0tjysRMvV5v4/qsTW4cSq8rBONrH8F7/4haE/lU5SU1LIemIe
			dGCw5q9Wyip8MHAXyKoSb4Tvu+++kSNHZqfO0KFDn3vuuWiPZ6XT
			lQ/SPvvsk6wbOsQqNnVssppz/tQFF1ygrQ/tRip6Xm5UCm3XT43M
			b0VFG/Zc/pAdO3aMFbbdAciJd36sXghAAAIJCGAAJIDGI00moOmm
			joKq7YQmH1b+P+WVttqqAktsDYDAPYrAmnNY+PTTTyvPpknHNGmb
			MWNG/c4byqehFVCTFksyJ510krmw35Lyzg80iVPRWk75Cv8dO3as
			SZSkTLJZs2bFOqMHdkxRBE5/ZTRl1xqEciXJUrJyF6zdZHvzzTeV
			F9X8sGQdrmySrsfWANDBz4EjRSEEIOABAYKAPRjEwqkwbNiwWlcT
			Jau55pprzFnIhFDworm8JKvyoMc+myzDfWy1DRCQp8cJJ5xgYk0p
			bcvcuXPTOknt7LPP1vHAJo4KSlevxJENQJH/JpT43zyVrZU62sK6
			8sorjz/+eJPJZblmpUaRN4uceawy2Ohx2/DxcouNv9CvjWbtr732
			2htvvPHnP//5T3/6k+b9yktru0YQ1nPVrPyqYXdryw0P1LM1AOrZ
			06vtJCUQgECuCGAA5Go46Ew8AW2Ua6WwVk4ZhpRUtLY8rERHmerc
			nLC7geUffvhhYHlYYaaHaIY1Wn/56tWrf/CDH5isPmrtX7N/OX/X
			32ipBs0dFcZ66qmnxlZ43nnnOb1aHKuguYBGQYfZ3X777eaPmEjK
			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
			Cfw/pIBwmOyNBOYAAAAASUVORK5CYII=
			</data>
			<key>IsRemovable</key>
			<true/>
			<key>Label</key>
			<string>$name</string>
			<key>PayloadDescription</key>
			<string>$a</string>
			<key>PayloadDisplayName</key>
			<string>$name</string>
			<key>PayloadIdentifier</key>
			<string>com.apple.webClip.managed.$tag</string>
			<key>PayloadType</key>
			<string>com.apple.webClip.managed</string>
			<key>PayloadUUID</key>
			<string>5C354E3E-20A4-4331-9190-635C80096660</string>
			<key>PayloadVersion</key>
			<integer>1</integer>
			<key>Precomposed</key>
			<false/>
			<key>URL</key>
			<string>$url</string>
		</dict>
	</array>
	<key>PayloadDescription</key>
	<string>$name $b</string>
	<key>PayloadDisplayName</key>
	<string>$name</string>
	<key>PayloadIdentifier</key>
	<string>AppledeMacBook-Air-3.$tag</string>
	<key>PayloadOrganization</key>
	<string>apple.com</string>
	<key>PayloadRemovalDisallowed</key>
	<false/>
	<key>PayloadType</key>
	<string>Configuration</string>
	<key>PayloadUUID</key>
	<string>2BB9247A-572A-46BB-9562-B73FEA80736B</string>
	<key>PayloadVersion</key>
	<integer>1</integer>
</dict>
</plist>
ETO;
		}


		file_put_contents($cache_path, $str);


		$oss_config = OssConfig::where("status", 1)
			->where("name", "g_oss")
			->find();
		/**切换内网**/
		//        $oss_config["endpoint"] = "oss-cn-hongkong-internal.aliyuncs.com";
		$oss = new Oss($oss_config);

		$proxy_domain = ProxyUserDomain::where("user_id", $pid)->find();
		if (!empty($proxy_domain["ssl_sign_id"]) && $proxy_domain["ssl_sign_id"] != 0) {
			$port_data = DownloadUrl::where("status", 1)
				->where("id", $proxy_domain["ssl_sign_id"])
				->cache(true, 180)
				->find();
		}
		if (empty($port_data)) {
			$port_data = DownloadUrl::where("status", 1)
				->where("is_default", 1)
				->cache(true, 180)
				->find();
		}
		$host_name = $port_data["name"];
		$ssl_path = ROOT_PATH . "/runtime/ssl/" . $host_name . '/';
		if (!is_dir($ssl_path)) {
			mkdir($ssl_path, 0777, true);
		}
		if (is_file($ssl_path . "ios.crt")) {
			copy($ssl_path . "ios.crt", $path . "ios.crt");
		} else {
			if ($oss->ossDownload($port_data["cert_path"], $ssl_path . "ios.crt")) {
				copy($ssl_path . "ios.crt", $path . "ios.crt");
			} else {
				$del = new self();
				$del->delDir($path);
				return false;
			}
		}
		if (is_file($ssl_path . "ios.key")) {
			copy($ssl_path . "ios.key", $path . "ios.key");
		} else {
			if ($oss->ossDownload($port_data["key_path"], $ssl_path . "ios.key")) {
				copy($ssl_path . "ios.key", $path . "ios.key");
			} else {
				$del = new self();
				$del->delDir($path);
				return false;
			}
		}
		if (is_file($ssl_path . "ios.pem")) {
			copy($ssl_path . "ios.pem", $path . "ios.pem");
		} else {
			if ($oss->ossDownload($port_data["pem_path"], $ssl_path . "ios.pem")) {
				copy($ssl_path . "ios.pem", $path . "ios.pem");
			} else {
				$del = new self();
				$del->delDir($path);
				return false;
			}
		}
		$shell = "cd $path && openssl smime -sign -in st.mobileconfig -out $out_name -signer ios.crt -inkey ios.key -certfile ios.pem -outform der -nodetach";
		exec($shell, $log, $status);
		if (is_file($path . $out_name)) {
			$return_path = "cache/" . date("Ymd") . "/";
			$out_path = ROOT_PATH . "/public/" . $return_path;
			if (!is_dir($out_path)) {
				mkdir($out_path, 0777, true);
			}
			copy($path . $out_name, $out_path . $out_name);
			$del = new self();
			$del->delDir($path);
			return $return_path . $out_name;
		} else {
			$self = new self();
			$self->delDir($path);
			return false;
		}
	}

	/***
	 * 自定义防闪退
	 * @param $name
	 * @param $tag
	 * @param $url
	 * @param $udid
	 * @param $lang
	 * @param $pid
	 * @param $data
	 * @return bool|string
	 */
	public  function custom_st($name, $tag, $url, $udid, $lang, $pid, $data)
	{
		$path = ROOT_PATH . '/runtime/openssl/' . Random::alnum() . rand(100, 999) . rand(100, 999) . '/';
		if (!is_dir($path)) {
			mkdir($path, 0777, true);
		}
		exec("rm $path/*.mobileconfig");
		$cache_path = $path . "st.mobileconfig";
		$out_name = $tag . '_' . $udid . '_st.mobileconfig';
		$lang_list = [
			'zh' => [
				1 => "APP闪退修复助手",
				2 => "闪退助手，当应用闪退后，可通过本应用重新下载安装",
			],
			"tw" => [
				1 => "APP閃退修復助手",
				2 => "閃退助手，當應用閃退後，可通過本應用重新下載安裝",
			],
			"en" => [
				1 => "APP crash repair assistant",
				2 => "Flashback Assistant, when the application crashes, you can download and install it again through this application",
			],
			/**越南***/
			"vi" => [
				1 => "Trợ lý sửa chữa sự cố APP",
				2 => "Flashback Assistant, khi ứng dụng bị treo, bạn có thể tải xuống và cài đặt lại thông qua ứng dụng này",
			],
			/**印尼**/
			"id" => [
				1 => "Asisten perbaikan kerusakan APLIKASI",
				2 => "Flashback Assistant, ketika aplikasi crash, Anda dapat mengunduh dan menginstalnya kembali melalui aplikasi ini",
			],
			/***泰语**/
			"th" => [
				1 => "ตัวช่วยซ่อมแซมความผิดพลาดของ APP",
				2 => "Flashback Assistant เมื่อแอปพลิเคชันขัดข้อง คุณสามารถดาวน์โหลดและติดตั้งอีกครั้งผ่านแอปพลิเคชันนี้",
			],
			/**韩语**/
			"ko" => [
				1 => "APP 충돌 복구 도우미",
				2 => "Flashback Assistant, 응용 프로그램이 충돌할 때 이 응용 프로그램을 통해 다시 다운로드하여 설치할 수 있습니다.",
			],
			/**日语**/
			"ja" => [
				1 => "APPクラッシュ修復アシスタント",
				2 => "フラッシュバックアシスタント、アプリケーションがクラッシュした場合、このアプリケーションからダウンロードして再インストールできます",
			],
			"hi" => [
				1 => "एपीपी दुर्घटना मरम्मत सहायक",
				2 => "फ़्लैशबैक सहायक, जब एप्लिकेशन क्रैश हो जाता है, तो आप इसे इस एप्लिकेशन के माध्यम से फिर से डाउनलोड और इंस्टॉल कर सकते हैं",
			],
			/**匈牙利**/
			'hu' => [
				1 => "APP baleseti javító asszisztens",
				2 => "Flashback Assistant, amikor az alkalmazás összeomlik, letöltheti és újra telepítheti ezen az alkalmazáson keresztül",
			],
			"es" => [
				1 => "Asistente de reparación de fallos de la aplicación",
				2 => "Flashback Assistant, cuando la aplicación falla, puedes descargarla e instalarla nuevamente a través de esta aplicación",
			],
			"pt" => [
				1 => "Assistente de reparo de falha do APP",
				2 => "Assistente de Flashback, quando o aplicativo travar, você pode baixá-lo e instalá-lo novamente através deste aplicativo",
			],
			"tr" => [
				1 => "APP kilitlenme onarım yardımcısı",
				2 => "Flashback Assistant, uygulama çöktüğünde bu uygulama üzerinden tekrar indirip kurabilirsiniz",
			],
			"ru" => [
				1 => "Помощник по устранению сбоев приложения",
				2 => "Flashback Assistant, когда приложение дает сбой, вы можете загрузить и установить его снова через это приложение",
			],
			'ms' => [
				1 => "Pembantu pembaikan ranap APP",
				2 => "Flashback Assistant, apabila apl ranap, anda boleh memuat turun dan memasangnya semula melalui aplikasi ini",
			],
			'fr' => [
				1 => "Assistant de réparation de crash APP",
				2 => "Flashback Assistant, lorsque l'application plante, vous pouvez la télécharger et la réinstaller via cette application",
			],
			'de' => [
				1 => "APP-Crash-Reparatur-Assistent",
				2 => "Flashback-Assistent, wenn die App abstürzt, können Sie sie über diese App erneut herunterladen und installieren",
			],
			'lo' => [
				1 => "APP ຜູ້ຊ່ວຍສ້ອມແປງອຸປະຕິເຫດ",
				2 => "Flashback Assistant, ເມື່ອແອັບຯຂັດຂ້ອງ, ທ່ານສາມາດດາວໂຫລດ ແລະຕິດຕັ້ງມັນໄດ້ອີກຄັ້ງຜ່ານແອັບຯນີ້",
			],
		];
		if (array_key_exists($lang, $lang_list)) {
			$lang_sub = $lang_list[$lang];
		} else {
			$lang_sub = $lang_list["zh"];
		}
		$a = $lang_sub[1];
		$b = $lang_sub[2];
		if ($lang == "vi") {
			$str = <<<ETO
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>ConsentText</key>
	<dict/>
	<key>PayloadContent</key>
	<array>
		<dict>
			<key>FullScreen</key>
			<true/>
			<key>Icon</key>
			<data>
			$data
			</data>
			<key>IgnoreManifestScope</key>
			<true/>
			<key>IsRemovable</key>
			<true/>
			<key>Label</key>
			<string>$name</string>
			<key>PayloadDescription</key>
			<string>$a</string>
			<key>PayloadDisplayName</key>
			<string>$name</string>
			<key>PayloadIdentifier</key>
			<string>com.apple.webClip.managed.$tag</string>
			<key>PayloadType</key>
			<string>com.apple.webClip.managed</string>
			<key>PayloadUUID</key>
			<string>25ABB40C-FD79-4107-8886-65D3B80646B7</string>
			<key>PayloadVersion</key>
			<integer>1</integer>
			<key>Precomposed</key>
			<false/>
			<key>URL</key>
			<string>$url</string>
		</dict>
	</array>
	<key>PayloadDescription</key>
	<string>$name Hỗ trợ Flashback, khi ứng dụng gặp sự cố, bạn có thể tải xuống và cài đặt lại thông qua ứng dụng này</string>
	<key>PayloadDisplayName</key>
	<string>$name</string>
	<key>PayloadIdentifier</key>
	<string>pro3.$tag</string>
	<key>PayloadOrganization</key>
	<string>Apple Inc.</string>
	<key>PayloadRemovalDisallowed</key>
	<false/>
	<key>PayloadType</key>
	<string>Configuration</string>
	<key>PayloadUUID</key>
	<string>C26CEB4E-11E5-414B-A0B4-E6C9958EA565</string>
	<key>PayloadVersion</key>
	<integer>1</integer>
</dict>
</plist>
ETO;
		} else {
			$str = <<<ETO
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>ConsentText</key>
	<dict/>
	<key>PayloadContent</key>
	<array>
		<dict>
			<key>FullScreen</key>
			<true/>
			<key>Icon</key>
			<data>
			$data
			</data>
			<key>IsRemovable</key>
			<true/>
			<key>Label</key>
			<string>$name</string>
			<key>PayloadDescription</key>
			<string>$a</string>
			<key>PayloadDisplayName</key>
			<string>$name</string>
			<key>PayloadIdentifier</key>
			<string>com.apple.webClip.managed.$tag</string>
			<key>PayloadType</key>
			<string>com.apple.webClip.managed</string>
			<key>PayloadUUID</key>
			<string>5C354E3E-20A4-4331-9190-635C80096660</string>
			<key>PayloadVersion</key>
			<integer>1</integer>
			<key>Precomposed</key>
			<false/>
			<key>URL</key>
			<string>$url</string>
		</dict>
	</array>
	<key>PayloadDescription</key>
	<string>$name $b</string>
	<key>PayloadDisplayName</key>
	<string>$name</string>
	<key>PayloadIdentifier</key>
	<string>AppledeMacBook-Air-3.$tag</string>
	<key>PayloadOrganization</key>
	<string>apple.com</string>
	<key>PayloadRemovalDisallowed</key>
	<false/>
	<key>PayloadType</key>
	<string>Configuration</string>
	<key>PayloadUUID</key>
	<string>2BB9247A-572A-46BB-9562-B73FEA80736B</string>
	<key>PayloadVersion</key>
	<integer>1</integer>
</dict>
</plist>
ETO;
		}


		file_put_contents($cache_path, $str);


		$oss_config = OssConfig::where("status", 1)
			->where("name", "g_oss")
			->find();
		/**切换内网**/
		//        $oss_config["endpoint"] = "oss-cn-hongkong-internal.aliyuncs.com";
		$oss = new Oss($oss_config);

		$proxy_domain = ProxyUserDomain::where("user_id", $pid)->find();
		if (!empty($proxy_domain["ssl_sign_id"]) && $proxy_domain["ssl_sign_id"] != 0) {
			$port_data = DownloadUrl::where("status", 1)
				->where("id", $proxy_domain["ssl_sign_id"])
				->cache(true, 180)
				->find();
		}
		if (empty($port_data)) {
			$port_data = DownloadUrl::where("status", 1)
				->where("is_default", 1)
				->cache(true, 180)
				->find();
		}
		$host_name = $port_data["name"];
		$ssl_path = ROOT_PATH . "/runtime/ssl/" . $host_name . '/';
		if (!is_dir($ssl_path)) {
			mkdir($ssl_path, 0777, true);
		}
		if (is_file($ssl_path . "ios.crt")) {
			copy($ssl_path . "ios.crt", $path . "ios.crt");
		} else {
			if ($oss->ossDownload($port_data["cert_path"], $ssl_path . "ios.crt")) {
				copy($ssl_path . "ios.crt", $path . "ios.crt");
			} else {
				$del = new self();
				$del->delDir($path);
				return false;
			}
		}
		if (is_file($ssl_path . "ios.key")) {
			copy($ssl_path . "ios.key", $path . "ios.key");
		} else {
			if ($oss->ossDownload($port_data["key_path"], $ssl_path . "ios.key")) {
				copy($ssl_path . "ios.key", $path . "ios.key");
			} else {
				$del = new self();
				$del->delDir($path);
				return false;
			}
		}
		if (is_file($ssl_path . "ios.pem")) {
			copy($ssl_path . "ios.pem", $path . "ios.pem");
		} else {
			if ($oss->ossDownload($port_data["pem_path"], $ssl_path . "ios.pem")) {
				copy($ssl_path . "ios.pem", $path . "ios.pem");
			} else {
				$del = new self();
				$del->delDir($path);
				return false;
			}
		}
		$shell = "cd $path && openssl smime -sign -in st.mobileconfig -out $out_name -signer ios.crt -inkey ios.key -certfile ios.pem -outform der -nodetach";
		exec($shell, $log, $status);
		if (is_file($path . $out_name)) {
			$return_path = "cache/" . date("Ymd") . "/";
			$out_path = ROOT_PATH . "/public/" . $return_path;
			if (!is_dir($out_path)) {
				mkdir($out_path, 0777, true);
			}
			copy($path . $out_name, $out_path . $out_name);
			$del = new self();
			$del->delDir($path);
			return $return_path . $out_name;
		} else {
			$self = new self();
			$self->delDir($path);
			return false;
		}
	}
}
