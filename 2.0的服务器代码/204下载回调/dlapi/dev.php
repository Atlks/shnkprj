<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-01
 * Time: 20:06
 */

return [
    'SERVER_NAME' => "EasySwoole",
    'MAIN_SERVER' => [
        'LISTEN_ADDRESS' => '0.0.0.0',
        'PORT' => 85,
        'SERVER_TYPE' => EASYSWOOLE_WEB_SERVER, //可选为 EASYSWOOLE_SERVER  EASYSWOOLE_WEB_SERVER EASYSWOOLE_WEB_SOCKET_SERVER,EASYSWOOLE_REDIS_SERVER
        'SOCK_TYPE' => SWOOLE_TCP,
        'RUN_MODEL' => SWOOLE_PROCESS,
        'SETTING' => [
            'worker_num' => 4,
            'task_worker_num' => 4,
            'reload_async' => true,
            'task_enable_coroutine' => true,
            'max_wait_time'=>3
        ],
    ],
    'TEMP_DIR' => null,
    'LOG_DIR' => null,
    /**MYSQL 配置**/
    'MYSQL' => [
        'host'          => '127.0.0.1',
        'port'          => '3306',
        'user'          => 'root',
        'timeout'       => '30',
        'charset'       => 'utf8mb4',
        'password'      => '8LtncsfXu87ff0p5',
        'database'      => 'ios',
        'POOL_MAX_NUM'  => '20',
        'POOL_MIN_NUM'  => '5',
        'POOL_TIME_OUT' => '0.1',
    ],
    /***Redis 配置**/
    'REDIS' => [
        'host'          => '127.0.0.1',
        'port'          => '6379',
        'auth'          => '',
        'select'        =>'2',
        'POOL_MAX_NUM'  => '20',
        'POOL_MIN_NUM'  => '5',
        'POOL_TIME_OUT' => '0.1',
    ],
    'PROXY_MYSQL' => [
        'host'          => '127.0.0.1',
        'port'          => '3306',
        'user'          => 'root',
        'timeout'       => '30',
        'charset'       => 'utf8mb4',
        'password'      => '8LtncsfXu87ff0p5',
        'database'      => 'dl',
        'POOL_MAX_NUM'  => '20',
        'POOL_MIN_NUM'  => '5',
        'POOL_TIME_OUT' => '0.1',
    ],
    "OSS"=>[
        'key' => 'LTAI4GATbyiMMhTq3emeM8JJ',
        'secret' => 'GsdgqPFQj3WBY8119jT5qPG2E3JxrK' ,
        'endpoint' => 'oss-accelerate.aliyuncs.com',
        'bucket' => 'testqkipa',
        'url' => 'https://testqkipa.oss-accelerate.aliyuncs.com/'
    ],
    "G_OSS"=>[
        'key' => 'LTAI4GATbyiMMhTq3emeM8JJ',
        'secret' => 'GsdgqPFQj3WBY8119jT5qPG2E3JxrK' ,
        'endpoint' => 'oss-accelerate.aliyuncs.com',
        'bucket' => 'testqkipa',
        'url' => 'https://testqkipa.oss-accelerate.aliyuncs.com/'
    ],
    "PROXY_OSS"=>[
        'key' => 'LTAI4GATbyiMMhTq3emeM8JJ',
        'secret' => 'GsdgqPFQj3WBY8119jT5qPG2E3JxrK' ,
        'endpoint' => 'oss-accelerate.aliyuncs.com',
        'bucket' => 'testqkipa',
        'url' => 'https://testqkipa.oss-accelerate.aliyuncs.com/'
    ],
    "PROXY_G_OSS"=>[
        'key' => 'LTAI4GATbyiMMhTq3emeM8JJ',
        'secret' => 'GsdgqPFQj3WBY8119jT5qPG2E3JxrK' ,
        'endpoint' => 'oss-accelerate.aliyuncs.com',
        'bucket' => 'testqkipa',
        'url' => 'https://testqkipa.oss-accelerate.aliyuncs.com/'
    ],


];
