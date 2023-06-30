<?php

use EasySwoole\Log\LoggerInterface;

return [
    'SERVER_NAME' => "EasySwoole",
    'MAIN_SERVER' => [
        'LISTEN_ADDRESS' => '0.0.0.0',
        'PORT' => 85,
        'SERVER_TYPE' => EASYSWOOLE_WEB_SERVER, //可选为 EASYSWOOLE_SERVER  EASYSWOOLE_WEB_SERVER EASYSWOOLE_WEB_SOCKET_SERVER
        'SOCK_TYPE' => SWOOLE_TCP,
        'RUN_MODEL' => SWOOLE_PROCESS,
        'SETTING' => [
            'worker_num' => 32,
            'reload_async' => true,
            'max_wait_time' => 3
        ],
        'TASK' => [
            'workerNum' => 32,
            'maxRunningNum' => 256,
            'timeout' => 15
        ]
    ],
    "LOG" => [
        'dir' => null,
        'level' => LoggerInterface::LOG_LEVEL_DEBUG,
        'handler' => null,
        'logConsole' => true,
        'displayConsole'=>true,
        'ignoreCategory' => []
    ],
    'TEMP_DIR' => null,
    'MYSQL' => [
        'host'          => '34.92.174.82',
        'port'          => '3306',
        'user'          => 'root',
        'timeout'       => '10',
        'charset'       => 'utf8mb4',
        'password'      => 'QK@e2e#sql3',
        'database'      => 'mdm',
        'POOL_MAX_NUM'  => '200',
        'POOL_MIN_NUM'  => '1',
        'POOL_TIME_OUT' => '10',
    ],
    /***Redis 配置**/
    'REDIS' => [
        'host'          => '34.96.144.207',
        'port'          => '6379',
        'auth'          => '9kE4eVFFcTtIv70M',
        'select'        =>'2',
        'POOL_MAX_NUM'  => '200',
        'POOL_MIN_NUM'  => '1',
        'POOL_TIME_OUT' => '10',
    ],
    /**OSS内网***/
    'G_OSS'=>[
        'key' => 'LTAI4GCo4d7yCmY9wysx1Tmu',
        'secret' => 'k0BwR7QBELJ7cr0Shc22ATydRtRby4' ,
        'endpoint' => 'oss-cn-hongkong-internal.aliyuncs.com',
        'own_endpoint'=>"hk.rrppqk.com",
        'bucket' => 'skyqa',
        'url' => 'https://hk.rrppqk.com/'
    ],
];
