<?php
namespace App\Lib;

use EasySwoole\EasySwoole\Config;
use Google\Cloud\Storage\StorageClient;


class GoogleOss
{

    protected $storage;
    protected $bucket;

    protected $projectId;

    protected $bucket_name;

    public $cdn;

    protected $keyFile;

    public function __construct($config=[])
    {
        if(empty($config)){
            $config = Config::getInstance()->getConf("Google_OSS");
        }
        $this->init($config);
        $this->storage = new StorageClient(['projectId' => $this->projectId, 'keyFile' => $this->keyFile]);
        $this->bucket = $this->storage->bucket($this->bucket_name);
    }

    public function init($config){
        $this->projectId = $config['projectId'];
        $this->bucket_name = $config['bucket'];
        $this->cdn = $config['cdn'];
        $this->keyFile = json_decode(file_get_contents(ROOT_PATH.$config['keyFile']),true);
    }


    /**
     * 上传谷歌云
     * @param $path
     * @param $savePath
     * @return bool
     */
    public function ossUpload($path, $savePath)
    {
        if (!is_file($path)) return false;
        $result = $this->bucket->upload(fopen($path, 'r'), ['name' => $savePath]);
        if ($result->exists()) {
            return $savePath;
        } else {
            return false;
        }
    }

    /**
     * 下载资源
     * @param $path
     * @param $savePath
     * @return bool
     */
    public function ossDownload($path, $savePath)
    {
        $object = $this->bucket->object($path);
        if ($object->exists()) {
            $dir = pathinfo($savePath, PATHINFO_DIRNAME);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $object->downloadToFile($savePath);
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否存在
     * @param $oss_path
     * @return bool
     */
    public function exists($oss_path){
        $object = $this->bucket->object($oss_path);
        if($object->exists()){
            return true;
        }else{
            return false;
        }
    }

    public function signUrl($oss_path){
        $object = $this->bucket->object($oss_path);
        return $object->signedUrl(new \DateTime('1 min'), ['version' => 'v4',]);
    }


}