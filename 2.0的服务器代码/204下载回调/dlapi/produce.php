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
            'worker_num' => 24,
            'task_worker_num' => 24,
            'reload_async' => true,
            'task_enable_coroutine' => true,
            'max_wait_time'=>10
        ],
    ],
    'TEMP_DIR' => null,
    'LOG_DIR' => null,
    /**MYSQL 配置**/
    'MYSQL' => [
        'host'          => '34.92.160.191',
        'port'          => '3306',
        'user'          => 'root',
        'timeout'       => '10',
        'charset'       => 'utf8',
        'password'      => 'QK@e23#eer',
        'database'      => 'kkios',
        'POOL_MAX_NUM'  => '200',
        'POOL_MIN_NUM'  => '5',
        'POOL_TIME_OUT' => '5',
    ],
    /***Redis 配置**/
    'REDIS' => [
        'host'          => '35.220.254.95',
        'port'          => '6379',
        'auth'          => 'G9VwbSzBC0',
        'select'        =>'2',
        'POOL_MAX_NUM'  => '100',
        'POOL_MIN_NUM'  => '5',
        'POOL_TIME_OUT' => '10',
    ],
    'PROXY_MYSQL' => [
        'host'          => '34.92.160.191',
        'port'          => '3306',
        'user'          => 'root',
        'timeout'       => '10',
        'charset'       => 'utf8',
        'password'      => 'QK@e23#eer',
        'database'      => 'ios',
        'POOL_MAX_NUM'  => '200',
        'POOL_MIN_NUM'  => '5',
        'POOL_TIME_OUT' => '5',
    ],

    "OSS"=>[
        'key' => 'LTAI4GATbyiMMhTq3emeM8JJ',
        'secret' => 'GsdgqPFQj3WBY8119jT5qPG2E3JxrK' ,
        'endpoint' => 'oss-accelerate.aliyuncs.com',
        'bucket' => 'iparesign',
        'url' => 'https://iparesign.oss-accelerate.aliyuncs.com/'
    ],
    "G_OSS"=>[
        'key' => 'LTAI4GATbyiMMhTq3emeM8JJ',
        'secret' => 'GsdgqPFQj3WBY8119jT5qPG2E3JxrK' ,
        'endpoint' => 'oss-accelerate.aliyuncs.com',
        'bucket' => 'qkgipa',
        'url' => 'https://qkgipa.oss-accelerate.aliyuncs.com/'
    ],

    "PROXY_OSS"=>[
        'key' => 'LTAI5tApYeZtNiqSetkos2sS',
        'secret' => '4JhikVX4IacCmelbENrzZuDPURivrk' ,
        'endpoint' => 'oss-accelerate.aliyuncs.com',
        'bucket' => 'ppdllcdoo',
        'url' => 'https://ppdllcdoo.oss-accelerate.aliyuncs.com/'
    ],
    "PROXY_G_OSS"=>[
        'key' => 'LTAI5tApYeZtNiqSetkos2sS',
        'secret' => '4JhikVX4IacCmelbENrzZuDPURivrk' ,
        'endpoint' => 'oss-accelerate.aliyuncs.com',
        'bucket' => 'ppdllhkoo',
        'url' => 'https://ppdllhkoo.oss-accelerate.aliyuncs.com/',
    ],

];
