<?php


namespace App\Lib;


use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;

class RedisLib
{

    public static function set($key,$value,$select=4,$timeOut=300){
        RedisPool::invoke(function (Redis $redis) use ($key,$value,$select,$timeOut) {
            $redis->select($select);
            $redis->set($key,json_encode($value),$timeOut);
        });
        return true;
    }

    public static function get($key,$select=4){
        $data =  RedisPool::invoke(function (Redis $redis) use ($key,$select) {
            $redis->select($select);
            return $redis->get($key);
        });
        if(!empty($data)){
            return json_decode($data,true);
        }else{
            return null;
        }
    }

    public static function hMSet($key,$value=[],$select=5){
        if(empty($value)){
            return  true;
        }
        return  RedisPool::invoke(function (Redis $redis) use ($key,$value,$select) {
            $redis->select($select);
           return  $redis->hMSet($key,$value);
        });
    }

    public static function hGetAll($key,$select=5){
       return RedisPool::invoke(function (Redis $redis)use($key,$select){
            $redis->select($select);
            return  $redis->hGetAll($key);
        });
    }

    public static function hUpdateVals($key,$value=[],$select=5){
        if(empty($value)){
            return  true;
        }
        RedisPool::invoke(function (Redis $redis)use($key,$value,$select){
            $redis->select($select);
            foreach ($value as $k=>$v){
                $redis->hSet($key,$k,$v);
            }
        });
        return  true;
    }

    public static function del($key,$select=5){
        if(empty($key)||$key=="*"){
            return  true;
        }
        return  RedisPool::invoke(function (Redis $redis) use ($key,$select) {
            $redis->select($select);
            return  $redis->del($key);
        });
    }


}