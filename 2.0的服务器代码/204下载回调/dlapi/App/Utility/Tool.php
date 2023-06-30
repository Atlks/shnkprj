<?php


namespace App\Utility;


use CFPropertyList\CFPropertyList;
use CFPropertyList\IOException;
use CFPropertyList\PListException;
use co;
use DOMException;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\MysqliPool\Connection;
use EasySwoole\MysqliPool\Mysql;
use OSS\Core\OssException;
use OSS\OssClient;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

class Tool
{

    /**
     * 文件是否存在
     * @param string $oss_path
     * @return bool
     */
    public function ossExit($oss_path=''){
        $ossConfig = Config::getInstance()->getConf('OSS');
        try {
            $ossClient = new OssClient($ossConfig['key'], $ossConfig['secret'], $ossConfig['endpoint']);
            $result=$ossClient->doesObjectExist($ossConfig['bucket'],$oss_path);
            return $result;
        } catch (OssException $e) {
            return false;
        }
    }

    /**
     * OSS下载文件
     * @param string $object
     * @param string $savePath
     * @return bool
     */
    public function ossDownload($object = '', $savePath = '')
    {
        //测试
        $ossConfig = Config::getInstance()->getConf('OSS');
        $options = array(
            OssClient::OSS_FILE_DOWNLOAD => $savePath
        );
        try {
            $ossClient = new OssClient($ossConfig['key'], $ossConfig['secret'], $ossConfig['endpoint']);
            $ossClient->getObject($ossConfig['bucket'], $object, $options);
            return true;
        } catch (OssException $e) {
            return false;
        }
    }

    /**
     * OSS存储上传
     * @param string $filePath
     * @param string $saveName
     * @return bool
     */
    public function ossUpload($filePath = '', $saveName = '')
    {
        $ossConfig = Config::getInstance()->getConf('OSS');
        try {
            $ossClient = new OssClient($ossConfig['key'], $ossConfig['secret'], $ossConfig['endpoint']);
            $ossClient->uploadFile($ossConfig['bucket'], $saveName, $filePath);
        } catch (OssException $e) {
            var_dump($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * 文件授权
     * @param $objects
     * @param $is_overseas
     * @return bool|string
     */
    public  function signOssUrl($objects,$is_overseas=10)
    {
        if($is_overseas==10){
            $ossConfig = Config::getInstance()->getConf('OSS');
            $name = "zh_oss_url";
        }else{
            $ossConfig = Config::getInstance()->getConf('G_OSS');
            $name = "en_oss_url";
        }
        $endpoint =  $ossConfig['endpoint'];
        $isCName = false;
        $url  = Mysql::invoker("mysql",function (Connection $db)use($name){
            return $db->where('name', $name)
                ->getValue("config","value");
        });
        /***有配置则使用自有域名***/
        if($url){
            $endpoint = $url;
            $isCName = true;
        }
        try {
            $ossClient = new OssClient($ossConfig['key'], $ossConfig['secret'], $endpoint,$isCName);
            $ossClient->setUseSSL(true);
            return $ossClient->signUrl($ossConfig['bucket'],$objects,300);
        } catch (OssException $e) {
            return false;
        }
    }

    /***
     * 代理加签
     * @param $objects
     * @param int $is_overseas
     * @return bool|string
     */
    public function proxySignOssUrl($objects,$is_overseas=10)
    {
        if($is_overseas==10){
            $ossConfig = Config::getInstance()->getConf('PROXY_OSS');
            $name = "proxy_zh_oss_url";
        }else{
            $ossConfig = Config::getInstance()->getConf('PROXY_G_OSS');
            $name = "proxy_en_oss_url";
        }
        $endpoint =  $ossConfig['endpoint'];
        $isCName = false;
        $url  = Mysql::invoker("mysql",function (Connection $db)use($name){
            return $db->where('name', $name)
                ->getValue("config","value");
        });
        /***有配置则使用自有域名***/
        if($url){
            $endpoint = $url;
            $isCName = true;
        }
        try {
            $ossClient = new OssClient($ossConfig['key'], $ossConfig['secret'],$endpoint,$isCName);
            $ossClient->setUseSSL(true);
            return $ossClient->signUrl($ossConfig['bucket'],$objects,300);
        } catch (OssException $e) {
            return false;
        }
    }

    /**
     * 获取文件列表
     * @param string $prefix
     * @return array|bool
     */
    public function listFile($prefix = '')
    {
        $ossConfig = Config::getInstance()->getConf('OSS');
        try {
            $ossClient = new OssClient($ossConfig['key'], $ossConfig['secret'], $ossConfig['endpoint']);
            $list = $ossClient->listObjects($ossConfig['bucket'], ['prefix' => $prefix])
                ->getObjectList();
            $listFiles = [];
            foreach ($list as $v) {
                $listFiles[] = $v->getKey();
            }
        } catch (OssException $e) {
            var_dump($e->getMessage());
            return false;
        }
        return $listFiles;
    }


    /**
     * 删除指定后缀文件
     * @param string $path
     * @param $file_type
     */
    public function clearFile($path = '', $file_type = '')
    {
        if (is_dir($path) && !empty($path) && strlen($path) > 5) {
            exec("rm -rf $path");
        }
        return true;
    }

    /**
     * 清除打包残余
     * @param array $log
     */
    public function delAusignCache($log = [])
    {
        foreach ($log as $v) {
            if (strpos($v, 'uuid') === 0) {
                $path = '/var/tmp/' . substr($v, 5);
                if (is_dir($path)) {
                    $this->clearFile($path);
                    break;
                } else {
                    continue;
                }
            }
        }
    }

    /***
     * @param string $udid
     * @param string $path
     * @param string $appName
     * @param array $extend
     * @return string
     * @throws IOException
     * @throws PListException
     * @throws DOMException
     */
    public function addUdidPlist($udid = '', $path = '', $appName = '', $extend = [])
    {
        $cachePath = $path . time();
        $zip = new ZipArchive();
        $zip->open($path . $appName);
        $zip->extractTo($cachePath);
        $zip->close();
        $zipFiles = scandir($cachePath . '/Payload');
        foreach ($zipFiles as $k => $val) {
            if ($val != '.' && $val !== '..' && is_dir($cachePath . '/Payload/' . $val)
                && is_file($cachePath . '/Payload/' . $val . '/Info.plist')
                && strstr($val, '.app')) {
                $fp = $cachePath . '/Payload/' . $val . '/Info.plist';
                break;
            }
        }
        // 获取plist文件内容
        $content = file_get_contents($fp);
        // 解析plist成数组
        $ipa = new CFPropertyList();
        $ipa->parse($content);
        $ipaInfo = $ipa->toArray();
        $ipaInfo['UDID'] = $udid;
        $ipaInfo = $this->array_no_empty(array_merge($ipaInfo, $extend));
        $plist = new PropertyList($ipaInfo);
        $xml = $plist->xml();
        file_put_contents($fp, $xml);
        unlink($path . $appName);
        $this->Zip($cachePath, $path . $appName);
        return $path . $appName;
    }

    /**
     * 压缩
     * @param $rootPath
     * @param $destination
     */
    public function Zip($rootPath, $destination)
    {

        $zip = new ZipArchive();
        $zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath));
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
    }

    /**
     * 添加证书描述文件
     * @param $account
     * @param $bundle_name
     * @param $udid
     * @return array|bool
     */
    public function getAddProvisioning($account, $bundle_name, $udid)
    {
        $soucre_path = ROOT_PATH . '/pcntl/' . uniqid() . rand(100, 999) . '/';
        if (!is_dir($soucre_path)) {
            mkdir($soucre_path, 0777, true);
        }
        $q = str_replace('@', 'sclc', $account['account']);
        $saveProvisioning = 'provisioning/' . $q . '.mobileprovision';
        if (empty($account['bundle'])) {
            $bundle_id = 'com.' . uniqid() . $q . '.www';
            if (strlen($bundle_id) > 120) {
                $bundle_id = 'com.sclichang' . uniqid() . time() . '.www';
            }
        } else {
            $bundle_id = $account['bundle'];
        }
        $provisioning_name = $bundle_id . '.mobileprovision';

        $this->ruby_sign($account['account'], $account['password'], $bundle_id, $bundle_name, $provisioning_name, $udid, substr($udid, -8), $soucre_path);
        $shell = "cd $soucre_path && ruby sign.rb";
        try {
            exec($shell, $log, $status);
        } catch (Throwable $exception) {
            var_dump('AccountID===============' . $account['id']);
            var_dump($exception->getMessage());
            $data = [
                'status' => 'fail',
            ];
            return $data;
        }
        if ($status == 0 && !in_array('DeviceMaximumNumber', $log) && !in_array("LoginError", $log)) {
            $ossUpload = $this->ossUpload($soucre_path . $provisioning_name, $saveProvisioning);
            exec("rm -rf $soucre_path");
            $update = [
                'status' => 'OK',
                'bundle' => $bundle_id,
                'provisioning' => $saveProvisioning
            ];
            return $update;
        } else {
            if (in_array("LoginError", $log)) {
                $data = [
                    'status' => 'Login',
                    'msg' => $log
                ];
                return $data;
            }
            if (in_array('DeviceMaximumNumber', $log)) {
                $data = [
                    'status' => 'device',
                    'msg' => $log
                ];
                return $data;
            }
            $data = [
                'status' => 'fail',
            ];
            return $data;
        }
    }

    /**
     * 添加ruby 脚本
     * @param string $account
     * @param string $password
     * @param string $bundle_id
     * @param string $app_name
     * @param string $provisioning_name
     * @param string $udid
     * @param string $udid_name
     * @param string $save_path
     */
    public function ruby_sign($account = '', $password = '', $bundle_id = '', $app_name = '', $provisioning_name = '', $udid = '', $udid_name = '', $save_path = '')
    {
//        $group_id = 'group.com.' . uniqid('ios') . str_replace('@', 'sc', $account) . '.www';
        $group_id = 'group.' . $bundle_id;
        $str = <<<ETO
#! /usr/bin/env ruby
require 'spaceship'

user_email='$account'
password='$password'
bundle_id='$bundle_id'
group_id='$group_id'
app_name='$app_name'
provisioning_name='$provisioning_name'
udid='$udid'
udid_name='$udid_name'

begin
Spaceship.login(user_email,password) #登录
rescue  Exception => e
puts 'LoginError'
puts e.message 
end


is_bundle = Spaceship.app.find(bundle_id)
if !is_bundle
  Spaceship.app.create!(bundle_id: bundle_id, name: app_name)
end

#group权限
app = Spaceship::Portal.app.find(bundle_id)
group = Spaceship::Portal.app_group.find(group_id)
if !group
    group = Spaceship::Portal.app_group.create!(group_id: group_id,name: "Another group")
end
app = app.associate_groups([group])
app.update_service(Spaceship::Portal.app_service.network_extension.on)
app.update_service(Spaceship::Portal.app_service.access_wifi.on)
app.update_service(Spaceship::Portal.app_service.push_notification.on)
app.update_service(Spaceship::Portal.app_service.game_center.on)
app.update_service(Spaceship::Portal.app_service.vpn_configuration.on)
app.update_service(Spaceship::Portal.app_service.siri_kit.on)
app.update_service(Spaceship::Portal.app_service.cloud_kit.cloud_kit)
app.update_service(Spaceship::Portal.app_service.hotspot.on)
app.update_service(Spaceship::Portal.app_service.health_kit.on)
app.update_service(Spaceship::Portal.app_service.associated_domains.on)

begin
#添加设备
Spaceship::Portal.device.create!(name: udid_name, udid: udid)

#更新设备
allDevices = Spaceship.device.all
device_profiles = Spaceship::Portal.provisioning_profile.ad_hoc.find_by_bundle_id(bundle_id: bundle_id)
device_profiles.each do |profile|
    profile.devices = allDevices
    profile.update!
end

rescue  Exception => e
puts 'DeviceMaximumNumber'
puts e.message 
end

#下载描述文件
matching_profiles = Spaceship::Portal.provisioning_profile.ad_hoc.find_by_bundle_id(bundle_id: bundle_id)
if matching_profiles.first
  File.write(provisioning_name, matching_profiles.first.download)
    else
       cert = Spaceship::Portal.certificate.production.all.first
       profile=Spaceship.provisioning_profile.ad_hoc.create!(bundle_id: bundle_id,certificate: cert,name: bundle_id)
    File.write(provisioning_name, profile.download)
end
 
ETO;
        file_put_contents($save_path . 'sign.rb', $str);
    }

    /**
     * 获取可用账号
     * @return bool|mixed
     */
    public function getAvailableAccount()
    {
        try {
            $redis = Redis::init();
            $account_id = [];
            $key_list = $redis->keys('inuse_account:*');
            if (!empty($key_list)) {
                $account_id = array_map(function ($val) {
                    return substr($val, strpos($val, ':') + 1);
                }, $key_list);
            }
            $redis->close();
            $account = Mysql::invoker('mysql', function (Connection $db) use ($account_id) {
                if (!empty($account_id)) {
                    $account_list = $db->where('udid_num', 99, '<=')
                        ->where('type', 1)
                        ->where('is_delete', 1)
                        ->where('status', 1)
                        ->where('create_time', date('Y-m-d H:i:s', time() - 25 * 24 * 60 * 60), '>')
                        ->whereNotIn('id', $account_id)
                        ->orderBy('sort', 'asc')
                        ->getColumn('account', 'id', 50);
                } else {
                    $account_list = $db->where('udid_num', 99, '<=')
                        ->where('type', 1)
                        ->where('is_delete', 1)
                        ->where('status', 1)
                        ->where('create_time', date('Y-m-d H:i:s', time() - 25 * 24 * 60 * 60), '>')
                        ->orderBy('sort', 'asc')
                        ->getColumn('account', 'id', 50);
                }
                if (empty($account_list)) {
                    return [];
                }
                $id = $account_list[array_rand($account_list)];
                $account = $db->where('id', $id)->getOne('account');
                return $account;
            });
            if (empty($account)) {
                co::sleep(6);
                $redis = Redis::init();
                $account_id = [];
                $key_list = $redis->keys('inuse_account:*');
                if (!empty($key_list)) {
                    $account_id = array_map(function ($val) {
                        return substr($val, strpos($val, ':') + 1);
                    }, $key_list);
                }
                $redis->close();
                $account = Mysql::invoker('mysql', function (Connection $db) use ($account_id) {
                    if (!empty($account_id)) {
                        $account_list = $db->where('udid_num', 99, '<=')
                            ->where('type', 1)
                            ->where('is_delete', 1)
                            ->where('status', 1)
                            ->where('create_time', date('Y-m-d H:i:s', time() - 25 * 24 * 60 * 60), '>')
                            ->whereNotIn('id', $account_id)
                            ->orderBy('sort', 'asc')
                            ->getColumn('account', 'id', 50);
                    } else {
                        $account_list = $db->where('udid_num', 99, '<=')
                            ->where('type', 1)
                            ->where('is_delete', 1)
                            ->where('status', 1)
                            ->where('create_time', date('Y-m-d H:i:s', time() - 25 * 24 * 60 * 60), '>')
                            ->orderBy('sort', 'asc')
                            ->getColumn('account', 'id', 50);
                    }
                    if (empty($account_list)) {
                        return [];
                    }
                    $id = $account_list[array_rand($account_list)];
                    $account = $db->where('id', $id)->getOne('account');
                    return $account;
                });
                if (!empty($account)) {
                    return $account;
                } else {
                    return false;
                }
            } else {
                return $account;
            }
        } catch (Throwable $exception) {
            return false;
        }
    }

    /**
     * 去除数组空空数组
     * @param $arr
     * @return array
     */
    public function array_no_empty($arr)
    {
        if (is_array($arr)) {
            foreach ($arr as $k => $v) {
                if (is_array($v) && empty($v)) {
                    unset($arr[$k]);
                } elseif (is_array($v)) {
                    $arr[$k] = $this->array_no_empty($v);
                }
            }
        }
        return $arr;
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
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    public function http_async_request($url = '', $data = [], $heard = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl, CURLOPT_HEADER, $heard);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    /**
     * 账号检测
     * @param $result
     * @param array $account
     * @param string $udid
     * @param array $post_data
     * @param string $url
     * @return bool
     */
    public function checkAddProvisioning($result, $account = [], $udid = '', $post_data = [], $url = '')
    {

        /**登录失败**/
        if ($result['status'] == 'Login') {
            $insert = [
                'account_id' => $account['id'],
                'account' => $account['account'],
                'msg' => implode("\r\n", $result['msg']),
                'create_time' => date('Y-m-d H:i:s')
            ];
            Mysql::invoker('mysql', function (Connection $db) use ($account, $insert) {
                $db->where('id', $account['id'])->update('account', ['status' => 0]);
                $db->insert('account_auto_obtained_log', $insert);
            });
            $url = 'http://127.0.0.1:' . Config::getInstance()->getConf('MAIN_SERVER.PORT') . $url;
            $re_sign = $this->http_async_request($url, $post_data);
            Logger::getInstance()->error("账号登录失败重新签名");
            return false;
        }
        /**udid添加失败**/
        if ($result['status'] == 'device') {
            Mysql::invoker('mysql', function (Connection $db) use ($account, $udid) {
//                $db->where('id',$account['id'])->update('account',['udid_num'=>100]);
//                $db->resetDbStatus();
                $db->where('udid', $udid)->update('download', ['account_id' => 1]);
                $db->resetDbStatus();
                $db->where('udid', $udid)->update('bale_rate', ['account_id' => 1]);
                $db->resetDbStatus();
                $db->where('udid', $udid)->update('proxy_download', ['account_id' => 1]);
                $db->resetDbStatus();
                $db->where('udid', $udid)->update('proxy_bale_rate', ['account_id' => 1]);
            });
            $url = 'http://127.0.0.1:' . Config::getInstance()->getConf('MAIN_SERVER.PORT') . $url;
            $re_sign = $this->http_async_request($url, $post_data);
            Logger::getInstance()->error(json_encode($result['msg']));
            Logger::getInstance()->error('账号udid数量已满');
            return false;
        }
        /**未知错误**/
        if ($result['status'] == 'fail') {
            Logger::getInstance()->error("获取描述文件失败");
            return false;
        }
        return true;
    }

    /**
     * 账号消耗添加
     * @param $app_id
     * @param $udid
     * @param $account_id
     * @param int $type
     * @return bool
     */
    public function expend($app_id, $udid, $account_id, $type = 1)
    {
        $data = [
            'account_id' => $account_id,
            'app_id' => $app_id,
            'udid' => $udid,
            'type' => $type,
            'create_time' => date('Y-m-d H:i:s')
        ];
        Mysql::invoker('mysql', function (Connection $db) use ($data) {
            $db->insert('account_expend', $data);
        });
        return true;
    }

    /**
     * 防闪退插件加入
     * @param string $path
     * @param string $bundle
     * @return bool
     * @throws IOException
     * @throws PListException
     * @throws DOMException
     */
    public function flashback($path = '', $bundle = '')
    {
        $plug_path = ROOT_PATH . '/other/ipaPlugIns';
        $this->copydir($plug_path, $path);
        $info_path = $path . '/PlugIns/VPNInterceptEXT.appex/Info.plist';
        $ipa = new CFPropertyList();
        $ipa->parse(file_get_contents($info_path));
        $infoPlist = $ipa->toArray();
        $infoPlist['CFBundleIdentifier'] = $bundle . '.Extension';
        $obj = $this->array_no_empty($infoPlist);
        $plist = new PropertyList($obj);
        $xml = $plist->xml();
        file_put_contents($info_path, $xml);
        return true;
    }

    /**
     *文件夹复制
     * @param $source
     * @param $dest
     */
    public function copydir($source, $dest)
    {
        if (!file_exists($dest)) mkdir($dest);
        $handle = opendir($source);
        while (($item = readdir($handle)) !== false) {
            if ($item == '.' || $item == '..') continue;
            $_source = $source . '/' . $item;
            $_dest = $dest . '/' . $item;
            if (is_file($_source)) copy($_source, $_dest);
            if (is_dir($_source)) $this->copydir($_source, $_dest);
        }
        closedir($handle);
    }

    /***
     * 刷机记录
     * @param $app_id
     * @param $is_proxy 1官网 2代理
     * @return bool
     */
    public function appAutoRefresh($app_id, $is_proxy)
    {
        $refresh = Mysql::invoker('mysql', function (Connection $db) use ($app_id, $is_proxy) {
            return $db->where('app_id', $app_id)
                ->where('is_proxy', $is_proxy)
                ->where('status', 1)
                ->getOne('auto_app_refush');
        });
        if (empty($refresh) || $refresh['scale'] == 0) {
            return true;
        }
        /***下载码检测**/
        $is_download_code_list = Mysql::invoker('mysql',function (Connection $db)use($app_id,$is_proxy){
            if($is_proxy==1){
                return $db->where('app_id',$app_id)
                    ->where('status',1)
                    ->getOne('download_code_list');
            }else{
                return $db->where('app_id',$app_id)
                    ->where('status',1)
                    ->getOne('proxy_download_code_list');
            }
        });
        if(!empty($is_download_code_list)){
            return  true;
        }
        $create_time = date('Y-m-d H:i:s', time() + rand(5, 15));
        $update_time = date('Y-m-d H:i:s', time() + rand(20, 60));
        $cdn = $ossConfig = Config::getInstance()->getConf('OSS.url');
        $rand = rand(1, 100);
        if ($refresh['scale'] == 100 || $rand >= $refresh['scale']) {
            $app = Mysql::invoker('mysql', function (Connection $db) use ($app_id, $is_proxy) {
                if ($is_proxy == 1) {
                    return $db->where('id', $app_id)->getOne('app');
                } else {
                    return $db->where('id', $app_id)->getOne('proxy_app');
                }
            });
            $user = Mysql::invoker('mysql', function (Connection $db) use ($app, $is_proxy) {
                if ($is_proxy == 1) {
                    return $db->where('id', $app['user_id'])->getOne('user');
                } else {
                    return $db->where('id', $app['user_id'])->getOne('proxy_user');
                }
            });
            if ($is_proxy == 1) {
                $list = Mysql::invoker('mysql', function (Connection $db) use ($app_id) {
                    return $db->join('account a', 'b.account_id=a.id', 'LEFT')
                        ->where('a.status', 1)
                        ->where('a.is_delete', 1)
                        ->whereNotNull('b.ip')
                        ->where('b.app_id', $app_id, '<>')
                        ->groupBy('b.udid')
                        ->getColumn('bale_rate b', 'b.udid', 100);
                });
                if ($user['money'] < 40 || empty($list) || $app['download_money'] > 0) {
                    return true;
                }
                $udid = array_rand($list);
                $is_downloda = Mysql::invoker('mysql', function (Connection $db) use ($udid, $app) {
                    return $db->where('udid', $udid)->where('tag', $app['tag'])->getOne('download');
                });
                if ($is_downloda) {
                    return true;
                }
                $num = Mysql::invoker('mysql', function (Connection $db) use ($app_id) {
                    return $db->where('status', 1)
                        ->where('app_id', $app_id)
                        ->count('bale_rate', 'id');
                });
                if ($app['download_limit'] != 0 && $num >= $app['download_limit']) {
                    return true;
                }
                $info = Mysql::invoker('mysql', function (Connection $db) use ($udid) {
                    return $db->where('udid', $udid)
                        ->whereNotNull('ip')
                        ->where('status', 1)
                        ->getOne('bale_rate');
                });
                $oss_save = 'signapp/' . $app['tag'] . '/' . $info['udid'] . '.ipa';
                /***先扣除金额***/
                $bale_rate = [
                    'app_id' => $app['id'],
                    'udid' => $info['udid'],
                    'user_id' => $app['user_id'],
                    'rate' => $user['rate'],
                    'status' => 1,
                    'create_time' => $create_time,
                    'update_time' => $update_time,
                    'account_id' => $info['account_id'],
                    'ip' => $info['ip'],
                    'device' => $info['device'],
                    'is_auto'=>1
                ];
                $download = [
                    'account_id' => $info['account_id'],
                    'app' => $app['name'],
                    'tag' => $app['tag'],
                    'user_id' => $app['user_id'],
                    'money' => $user['rate'],
                    'app_path' => $oss_save,
                    'udid' => $info['udid'],
                    'version' => $app['version_code'],
                    'download' => $cdn . $oss_save,
                    'create_time' => $create_time,
                    'update_time' => $update_time,
                    'ip' => $info['ip'],
                    'device' => $info['device']
                ];
                Mysql::invoker('mysql', function (Connection $db) use ($user, $bale_rate, $download, $app_id) {

                    $db->where('id', $user['id'])
                        ->update('user', ['money' => $db->dec($user['rate'])]);
                    $db->resetDbStatus();
                    $db->insert('bale_rate', $bale_rate);
                    $db->resetDbStatus();
                    $db->insert('download', $download);
                    $db->resetDbStatus();
                    $db->where('id', $app_id)
                        ->update('app', ['download_num' => $db->inc(1), 'pay_num' => $db->inc(1)]);
                });
            } else {
                $list = Mysql::invoker('mysql', function (Connection $db) use ($app_id) {
                    return $db->join('account a', 'b.account_id=a.id', 'LEFT')
                        ->where('a.status', 1)
                        ->where('a.is_delete', 1)
                        ->whereNotNull('b.ip')
                        ->where('b.app_id', $app_id, '<>')
                        ->groupBy('b.udid')
                        ->getColumn('proxy_bale_rate b', 'b.udid', 100);
                });
                //TODO::代理次数修改
                if ($user['sign_num'] < 2 || empty($list) || $app['download_money'] > 0) {
                    return true;
                }
                $udid = array_rand($list);
                $is_downloda = Mysql::invoker('mysql', function (Connection $db) use ($udid, $app) {
                    return $db->where('udid', $udid)->where('tag', $app['tag'])->getOne('proxy_download');
                });
                if ($is_downloda) {
                    return true;
                }
                $num = Mysql::invoker('mysql', function (Connection $db) use ($app_id) {
                    return $db->where('status', 1)
                        ->where('app_id', $app_id)
                        ->count('proxy_bale_rate', 'id');
                });
                if ($app['download_limit'] != 0 && $num >= $app['download_limit']) {
                    return true;
                }
                $info = Mysql::invoker('mysql', function (Connection $db) use ($udid) {
                    return $db->where('udid', $udid)
                        ->whereNotNull('ip')
                        ->where('status', 1)
                        ->getOne('proxy_bale_rate');
                });
                $oss_save = 'signapp/' . $app['tag'] . '/' . $info['udid'] . '.ipa';
                //TODO::代理次数修改
                /***先扣除金额***/
                $bale_rate = [
                    'app_id' => $app['id'],
                    'udid' => $info['udid'],
                    'user_id' => $app['user_id'],
                    'rate' => $user['rate'],
                    'pid' => $user['pid'],
                    'status' => 1,
                    'create_time' => $create_time,
                    'update_time' => $update_time,
                    'account_id' => $info['account_id'],
                    'ip' => $info['ip'],
                    'device' => $info['device'],
                    'sign_num' => 1,
                    'is_auto'=>1
                ];
                $download = [
                    'account_id' => $info['account_id'],
                    'app' => $app['name'],
                    'tag' => $app['tag'],
                    'user_id' => $app['user_id'],
                    'money' => $user['rate'],
                    'app_path' => $oss_save,
                    'udid' => $info['udid'],
                    'version' => $app['version_code'],
                    'download' => $cdn . $oss_save,
                    'create_time' => $create_time,
                    'update_time' => $update_time,
                    'ip' => $info['ip'],
                    'device' => $info['device'],
                    'pid' => $user['pid'],
                ];
                //TODO::代理次数修改
                Mysql::invoker('mysql', function (Connection $db) use ($user, $bale_rate, $download, $app_id) {
                    $db->where('id', $user['id'])
                        ->update('proxy_user', ['sign_num' => $db->dec(1)]);
                    $db->resetDbStatus();
                    $db->insert('proxy_bale_rate', $bale_rate);
                    $db->resetDbStatus();
                    $db->insert('proxy_download', $download);
                    $db->resetDbStatus();
                    $db->where('id', $app_id)
                        ->update('proxy_app', ['download_num' => $db->inc(1), 'pay_num' => $db->inc(1)]);

                });
            }
        }
        return true;
    }

    public function getTable($table="",$user_id=""){
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), 10));
        return $table."_".$ext;
    }

}