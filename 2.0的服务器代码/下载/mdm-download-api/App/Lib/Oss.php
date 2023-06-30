<?php


namespace App\Lib;


use EasySwoole\EasySwoole\Logger;
use EasySwoole\Oss\AliYun\Config;
use EasySwoole\Oss\AliYun\OssClient;
use EasySwoole\Oss\AliYun\OssConst;

class Oss
{
    public function signUrl($url = "")
    {
        $config = \EasySwoole\EasySwoole\Config::getInstance()->getConf('G_OSS');
        $ossConfig = new Config();
        $ossConfig->setAccessKeyId($config["key"]);
        $ossConfig->setAccessKeySecret($config["secret"]);
        $ossConfig->setEndpoint($config["endpoint"]);
        $ossConfig->setIsCName(true);
        $ossClient = new OssClient($ossConfig);
        $ossClient->setUseSSL(true);
        return $ossClient->signUrl($config["bucket"], $url, 60);
    }

    /**oss 下载***/
    public function download($object = "", $savePath = "")
    {
        $options = array(
            OssConst::OSS_FILE_DOWNLOAD => $savePath
        );
        $config = \EasySwoole\EasySwoole\Config::getInstance()->getConf('G_OSS');
        $ossConfig = new Config();
        $ossConfig->setAccessKeyId($config["key"]);
        $ossConfig->setAccessKeySecret($config["secret"]);
        $ossConfig->setEndpoint($config["endpoint"]);
        $ossClient = new OssClient($ossConfig);
        $ossClient->getObject($config["bucket"], $object, $options);
        if(is_file($savePath)){
            return true;
        }else{
            return false;
        }
    }

}