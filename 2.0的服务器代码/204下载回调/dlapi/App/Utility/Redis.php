<?php


namespace App\Utility;


use EasySwoole\EasySwoole\Config;

class Redis
{
    public function __construct()
    {

    }

    public static function init(){
        $redisConfig = Config::getInstance()->getConf('REDIS');
        $redis = new \Swoole\Coroutine\Redis();
        $redis->connect($redisConfig['host'],$redisConfig['port']);
        $redis->auth($redisConfig['auth']);
        $redis->select($redisConfig['select']);
        return $redis;
    }

}