<?php


namespace App\Lib;


use EasySwoole\EasySwoole\Logger;
use EasySwoole\Oss\AliYun\Config;
use EasySwoole\Oss\AliYun\OssClient;
use EasySwoole\Oss\AliYun\OssConst;

class Oss
{

    protected $ossConfig;

    public function __construct($config=[])
    {
        $this->ossConfig =$config;
    }

    public function signUrl($url = "")
    {
       // $config = \EasySwoole\EasySwoole\Config::getInstance()->getConf('G_OSS');
        $ossConfig = new Config();
        $ossConfig->setAccessKeyId($this->ossConfig["key"]);
        $ossConfig->setAccessKeySecret($this->ossConfig["secret"]);
        $ossConfig->setEndpoint($this->ossConfig["own_endpoint"]);
        $ossConfig->setIsCName(true);
        $ossClient = new OssClient($ossConfig);
        $ossClient->setUseSSL(true);
        return $ossClient->signUrl($this->ossConfig["bucket"], $url, 120);
    }

    /**oss 下载***/
    public function download($object = "", $savePath = "")
    {
        $options = array(
            OssConst::OSS_FILE_DOWNLOAD => $savePath
        );
       // $config = \EasySwoole\EasySwoole\Config::getInstance()->getConf('G_OSS');
        $ossConfig = new Config();
        $ossConfig->setAccessKeyId($this->ossConfig["key"]);
        $ossConfig->setAccessKeySecret($this->ossConfig["secret"]);
        $ossConfig->setEndpoint($this->ossConfig["endpoint"]);
        $ossClient = new OssClient($ossConfig);
        $ossClient->getObject($this->ossConfig["bucket"], $object, $options);
        if(is_file($savePath)){
            return true;
        }else{
            return false;
        }
    }

    public function is_exit($object){
        $ossConfig = new Config();
        $ossConfig->setAccessKeyId($this->ossConfig["key"]);
        $ossConfig->setAccessKeySecret($this->ossConfig["secret"]);
        $ossConfig->setEndpoint($this->ossConfig["endpoint"]);
        $ossClient = new OssClient($ossConfig);
        $is_exit = $ossClient->doesObjectExist($this->ossConfig["bucket"], $object);
        if($is_exit){
            return true;
        }else{
            return false;
        }
    }
}