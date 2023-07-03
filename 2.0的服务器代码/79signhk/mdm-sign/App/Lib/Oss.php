<?php


namespace App\Lib;


use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use OSS\Core\OssException;
use OSS\OssClient;

class Oss
{
    protected $ossConfig;

    public function __construct($config=[])
    {
        if(empty($config)){
            $this->ossConfig = Config::getInstance()->getConf('G_OSS');
        }else{
            $this->ossConfig =$config;
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
        clearstatcache();
        $options = array(
            OssClient::OSS_FILE_DOWNLOAD => $savePath
        );
        try {
            $ossClient = new OssClient($this->ossConfig['key'], $this->ossConfig['secret'], $this->ossConfig['endpoint']);
            $ossClient->getObject($this->ossConfig['bucket'], $object, $options);
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
        clearstatcache();
        try {
            $ossClient = new OssClient($this->ossConfig['key'], $this->ossConfig['secret'], $this->ossConfig['endpoint']);
            $ossClient->uploadFile($this->ossConfig['bucket'], $saveName, $filePath);
        } catch (OssException $e) {
            Logger::getInstance()->error($e->getErrorMessage());
            return false;
        }
        return true;
    }

    /**
     * oss签名
     * @param $url
     * @return bool|string
     */
    public function signUrl($url){
        try{
            $ossClient = new OssClient($this->ossConfig['key'], $this->ossConfig['secret'], $this->ossConfig['own_endpoint'],true);
            $ossClient->setUseSSL(true);
            $signUrl = $ossClient->signUrl($this->ossConfig["bucket"],$url,300);
            return $signUrl;
        } catch(OssException $e) {
            return false;
        }
    }

    /**
     * 加参数签名
     * @param $url
     * @param null $options
     * @return bool|string
     */
    public function signUrlOptions($url,$options=null){
        try{
            $ossClient = new OssClient($this->ossConfig['key'], $this->ossConfig['secret'], $this->ossConfig['own_endpoint'],true);
            $ossClient->setUseSSL(true);
            $signUrl = $ossClient->signUrl($this->ossConfig["bucket"],$url,300,"GET",$options);
            return $signUrl;
        } catch(OssException $e) {
            return false;
        }
    }

    public function oss_url(){
        return $this->ossConfig["url"];
    }
}