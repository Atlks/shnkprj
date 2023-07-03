<?php
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
            'max_wait_time'=>3
        ],
        'TASK'=>[
            'workerNum'=>32,
            'maxRunningNum'=>128,
            'timeout'=>15
        ]
    ],
    'TEMP_DIR' => null,
    'LOG_DIR' => null,
    'G_OSS'=>[
        'key' => 'LTAI5tH2gLxNXdnBa1r48NSW',
        'secret' => 'qION2kleswPHwoEQBXH6snjH90znpC' ,
        'endpoint' => 'oss-cn-shenzhen-internal.aliyuncs.com',
        'bucket' => 'kkhhhiissz',
        'own_endpoint'=>"kkhhhiissz.oss-accelerate.aliyuncs.com",
        'url' => 'https://kkhhhiissz.oss-accelerate.aliyuncs.com/',
    ],
    'MYSQL' => [
        'host'          => '34.92.174.82', //外网
        'port'          => '3306',
        'user'          => 'root',
        'timeout'       => '10',
        'charset'       => 'utf8mb4',
        'password'      => 'QK@e2e#sql3',
        'database'      => 'mdm',
        'POOL_MAX_NUM'  => '10',
        'POOL_MIN_NUM'  => '1',
        'POOL_TIME_OUT' => '10',
    ],
    /***Redis 不需要 配置**/
    'REDIS' => [
        'host'          => 'r-j6cbvg0qreu2atugripd.redis.rds.aliyuncs.com',
        'port'          => '6379',
        'auth'          => 'Rz1zLfr6NfeAtbX2',
        'select'        =>'2',
        'POOL_MAX_NUM'  => '10',
        'POOL_MIN_NUM'  => '1',
        'POOL_TIME_OUT' => '30',
    ],
    /**数据回调地址**/
    'API_URL'=>"http://35.241.123.37:85/",
];
