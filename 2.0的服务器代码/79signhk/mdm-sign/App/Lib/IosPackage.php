<?php


namespace App\Lib;


use CFPropertyList\CFPropertyList;
use CFPropertyList\IOException;
use CFPropertyList\PListException;
use Chumper\Zipper\Zipper;
use DOMException;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use PhpZip\ZipFile;
use Throwable;
use ZipArchive;

class IosPackage
{
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


    /**
     * 获取包信息
     * @param string $app_path
     * @return array|bool
     */
    public function getIosPackage($app_path = '',$user_id=0)
    {
        // 遍历zip包中的Info.plist文件
        $cacheZipPath = ROOT_PATH . '/cache/app/' . uniqid();
        if (!is_dir($cacheZipPath)) {
            mkdir($cacheZipPath, 0777, true);
        }
        $save_path = $cacheZipPath . "/cache.ipa";
        $oss = new Oss();
        if (!$oss->ossDownload($app_path, $save_path)) {
            return false;
        }
        try {
            exec("cd $cacheZipPath && unzip -O gb2312 cache.ipa");
            if(!is_dir($cacheZipPath . '/Payload')){
                $this->delDir($cacheZipPath);
                return false;
            }
            $tmp_dir_list = scandir($cacheZipPath . '/Payload');
            $list = [];
            $app_folder = "";
            foreach ($tmp_dir_list as $k => $v) {
                if ($v != '.' && $v != '..' && is_dir($cacheZipPath . '/Payload/' . $v) && strstr($v, '.app')) {
                    $app_folder = $v;
                    $list[] = 'Payload/' . $v . "/Info.plist";
                    break;
                }
            }
            $icon_cache_path = $cacheZipPath . '/Payload/' . $app_folder;
            $icon_dir = scandir($icon_cache_path);
            foreach ($icon_dir as $k => $v) {
                if ($v != '.' && $v != '..' && is_file($icon_cache_path . '/' . $v) && strstr($v, '.png')) {
                    $list[] = 'Payload/' . $app_folder . "/$v";
                }
            }
            foreach ($list as $k => $filePath) {
                // 正则匹配包根目录中的Info.plist文件
                if (preg_match("/Payload\/([^\/]*)\/Info\.plist$/i", $filePath, $matches)) {
                    $app_folder = $matches[1];

                    // 拼接plist文件完整路径
                    $fp = $cacheZipPath . '/Payload/' . $app_folder . '/Info.plist';
                    if (!is_file($fp)) {
                        $this->delDir($cacheZipPath);
                        return false;
//                        exec("cd $cacheZipPath && unzip -O CP936 cache.ipa");
                    }
                    // 获取plist文件内容
                    $content = file_get_contents($fp);

                    // 解析plist成数组
                    $ipa = new CFPropertyList();
                    $ipa->parse($content);
                    $ipaInfo = $ipa->toArray();
                    //ipa icon
                    if (isset($ipaInfo['CFBundleIcons']['CFBundlePrimaryIcon']['CFBundleIconFiles']) &&
                        array_filter($ipaInfo['CFBundleIcons']['CFBundlePrimaryIcon']['CFBundleIconFiles'])) {
//                        $icon = end($ipaInfo['CFBundleIcons']['CFBundlePrimaryIcon']['CFBundleIconFiles']);
                        $icon_set_cache = $ipaInfo['CFBundleIcons']['CFBundlePrimaryIcon']['CFBundleIconFiles'];
                        $icon_set_cache = array_slice($icon_set_cache,-3,3);
                        $icon = $icon_set_cache[array_rand($icon_set_cache)];
                    } elseif (isset($ipaInfo['CFBundleIcons']['CFBundlePrimaryIcon']) &&
                        array_filter($ipaInfo['CFBundleIcons']['CFBundlePrimaryIcon'])) {
//                        $icon = end($ipaInfo['CFBundleIcons']['CFBundlePrimaryIcon']);
                        $icon_set_cache = $ipaInfo['CFBundleIcons']['CFBundlePrimaryIcon'];
                        $icon_set_cache = array_slice($icon_set_cache,-3,3);
                        $icon = $icon_set_cache[array_rand($icon_set_cache)];
                    } elseif (isset($ipaInfo['CFBundleIcons']) && array_filter($ipaInfo['CFBundleIcons'])) {
//                        $icon = end($ipaInfo['CFBundleIcons']);
                        $icon_set_cache = $ipaInfo['CFBundleIcons'];
                        $icon_set_cache = array_slice($icon_set_cache,-3,3);
                        $icon = $icon_set_cache[array_rand($icon_set_cache)];
                    } elseif (isset($ipaInfo['CFBundleIconFiles']) && array_filter($ipaInfo['CFBundleIconFiles'])) {
//                        $icon = end($ipaInfo['CFBundleIconFiles']);
                        $icon_set_cache = $ipaInfo['CFBundleIconFiles'];
                        $icon_set_cache = array_slice($icon_set_cache,-3,3);
                        $icon = $icon_set_cache[array_rand($icon_set_cache)];
                    } else {
                        foreach ($list as $key => $value) {
                            if (strstr($value, 'AppIcon')) {
                                $icon = strstr($value, 'AppIcon');
                                continue;
                            }
                            if (strstr($value, 'Icon')) {
                                $icon = strstr($value, 'Icon');
                                continue;
                            }
                        }
                    }
                    $icon_path = $this->getIcon($cacheZipPath . '/Payload/' . $app_folder, $icon);
                    if ($icon_path && is_file($icon_path)) {
                        $icon_path = $this->getIconConversion($icon_path);
                    } else {
                        $icon = $cacheZipPath . '/' . end($iconFiles);
                        if (is_file($icon)) {
                            $icon_path = $this->getIconConversion($icon);
                        } else {
                            $icon_path = '';
                        }
                    }
                    // ipa 解包信息
                    $ipa_data_bak = $ipaInfo;
                    // 包名
                    $result['package_name'][] = $ipaInfo['CFBundleIdentifier'];

                    // 版本名
                    $version_code = $ipaInfo['CFBundleShortVersionString'];

                    // 版本号
                    $version_name = str_replace('.', '', $ipaInfo['CFBundleShortVersionString']);

                    // 别名
                    $bundle_name = isset($ipaInfo['CFBundleName']) ? $ipaInfo['CFBundleName'] : $ipaInfo['CFBundleDisplayName'];

                    // 显示名称
                    $display_name = isset($ipaInfo['CFBundleDisplayName']) ? $ipaInfo['CFBundleDisplayName'] : $ipaInfo['CFBundleName'];
                    unset($ipa_data_bak['CFBundleIcons']);
                    unset($ipa_data_bak['UILaunchImages']);
                    unset($ipa_data_bak['CFBundleIcons~ipad']);
                    unset($ipa_data_bak['UISupportedInterfaceOrientations~ipad']);
                    unset($ipa_data_bak['UISupportedInterfaceOrientations']);
                    unset($ipa_data_bak['LSApplicationQueriesSchemes']);
                    unset($ipa_data_bak['NSAppTransportSecurity']);
                    unset($ipa_data_bak['microChatWelcomeImage']);
                    $data = [
                        "filesize" => filesize($save_path),
                        'icon' => $icon_path,
                        'ipa_data_bak' => $ipa_data_bak,
                        'version_name' => $version_name,
                        'version_code' => $version_code,
                        'bundle_name' => $bundle_name,
                        'display_name' => $display_name,
                    ];
                    $result = array_merge($data, $result);
                    continue;
                } else {
                    continue;
                }
            }
            if (is_dir($cacheZipPath. '/Payload/' . $app_folder."/Frameworks")){
                $is_zx =  scandir($cacheZipPath. '/Payload/' . $app_folder."/Frameworks");
                foreach ($is_zx as $v){
                    if ($v != '.' && $v != '..' && $v=="ZXRequestBlock.framework" && is_dir($cacheZipPath. '/Payload/' . $app_folder."/Frameworks/ZXRequestBlock.framework")) {
//                        $url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
                        $url ="http://35.241.123.37:85/api/send_bot_token_message";
                        $post_data = [
                            "token"=>'1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc',
                            "chat_id" => "-1001463689548",
                            "text" => "APP: ".$result['display_name']." ，存在 < ZXRequestBlock.framework > 注入库，用户ID：".$user_id." 及时查看",
                        ];
                        $tool = new Tool();
                        $tel_result = $tool->http_client($url, $post_data);
                        $result["is_error_dlib"]=1;
                    }
                }
            }
            $this->delDir($cacheZipPath);
            $result['package_name'] = implode(',', $result['package_name']);
            return $result;
        } catch (Throwable $exception) {
            $this->delDir($cacheZipPath);
            return false;
        }
    }

    /**
     * 查找ICON
     * @param string $path
     * @param string $icon_name
     * @return bool|string
     */
    public function getIcon($path = '', $icon_name = '')
    {
        $filesnames = scandir($path);
        foreach ($filesnames as $k => $v) {
            if (strpos($v, $icon_name) === 0) {
                return $path . '/' . $v;
            } else {
                if ($v == '.' || $v == '..') {
                    continue;
                }
                $dir = $path . '/' . $v;
                if (is_dir($dir)) {
                    $this->getIcon($dir, $icon_name);
                } else {
                    continue;
                }
            }
        }
        return false;
    }

    /**
     * 图片解码
     * @param string $icon_path
     * @return string
     */
    public function getIconConversion($icon_path = '')
    {
        /***检测图片是否正常**/
        $file_name = trim(strrchr($icon_path, '/'), '/');
        $path = ROOT_PATH . '/cache/icon/' . uniqid() . rand(111, 999) . '/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        copy(ROOT_PATH . '/other/ipin.py', $path . 'ipin.py');
        copy($icon_path, $path . $file_name);
        $exec = "cd $path && /usr/bin/python2 ipin.py";
        exec($exec, $log, $status);
        $cache_path = $path . $file_name;
        $save_path = "cache-upload/" . date("Ymd") . "/" . md5_file($cache_path) . ".png";
        $oss = new Oss();
        $oss->ossUpload($cache_path, $save_path);

        $oss_read = new Oss(Config::getInstance()->getConf('G_OSS_READ'));
        $oss_read->ossUpload($cache_path, $save_path);
        return $save_path;
    }


    /**
     * 大文件复制
     * @param string $source
     * @param string $dist
     * @return bool
     */
    public function streamToFile($source = '', $dist = '')
    {
        $soucre_fp = fopen($source, 'r');
        $dist_fp = fopen($dist, 'w+');
        stream_copy_to_stream($soucre_fp, $dist_fp);
        fclose($dist_fp);
        fclose($soucre_fp);
        return true;
    }

    /**
     * 获取加密描述文件
     * @param string $app_name
     * @param string $tag
     * @param string $callback
     * @param string $version
     * @param string $bundle
     * @param null $outpath
     * @param array $extend
     * @param boolean $is_js
     * @return bool|string
     */
    public static function getMobileConfig($app_name = '', $tag = '', $callback = '', $version = '1', $bundle = 'dev.skyfox.profile-service', $extend = [])
    {
        $name = $tag . '.mobileconfig';
        $path = ROOT_PATH . '/Temp/openssl/' . uniqid() . '/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        } else {
            exec("rm $path/*.mobileconfig");
        }
        $cache_path = $path . $name;
        $out_name = $tag . '_sign.mobileconfig';
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
        <string>授权安装进入下一步</string>
        <key>PayloadDisplayName</key>
        <string>$app_name --【点击安装】</string>
        <key>PayloadVersion</key>
        <integer>1</integer>
        <key>PayloadUUID</key>
        <string>3C4DC7D2-E475-3375-489C-0BB8D737A653</string>
        <key>PayloadIdentifier</key>
        <string>$bundle</string>
        <key>PayloadDescription</key>
        <string>该配置文件帮助用户进行APP授权安装！This configuration file helps users with APP license installation!</string>
        <key>PayloadType</key>
        <string>Profile Service</string>
    </dict>
</plist>
ETO;
        file_put_contents($cache_path, $str);
        $oss = new Tool();
        if ($oss->ossDownload($extend['pem_path'], $path . 'ios.pem') &&
            $oss->ossDownload($extend['cert_path'], $path . 'ios.crt') &&
            $oss->ossDownload($extend['key_path'], $path . 'ios.key')) {
            $shell = "cd $path && openssl smime -sign -in $name -out $out_name -signer ios.crt -inkey ios.key -certfile ios.pem -outform der -nodetach";
            exec($shell, $log, $status);
            if ($status == 0) {
                $save_name = "mobileconfig/proxy/" . date('Ymd') . "/" . $out_name;
                if ($oss->ossUpload($path . $out_name, $save_name)) {
                    return $save_name;
                }
            }
        }
        $del = new self();
        $del->delDir($path);
        return false;
    }


}