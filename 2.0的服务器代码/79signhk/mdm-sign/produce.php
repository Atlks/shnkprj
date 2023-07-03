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
        /*'key' => 'LTAI5t7gVs4h3Rzgvsoqfefj',
        'secret' => 'Nzen7ucD9uDrvlpASdXQGiA87ZxkK0' ,
        'endpoint' => 'oss-cn-hongkong-internal.aliyuncs.com',
        'own_endpoint'=>"hkqmapprool.oss-accelerate.aliyuncs.com",
        'bucket' => 'hkqmapprool',
        'url' => 'https://hkqmapprool.oss-accelerate.aliyuncs.com/'*/
        /*
        'key' => 'LTAI5t7gVs4h3Rzgvsoqfefj',
        'secret' => 'Nzen7ucD9uDrvlpASdXQGiA87ZxkK0' ,
        'endpoint' => 'oss-cn-hongkong-internal.aliyuncs.com',
        'own_endpoint'=>"hkqmrool.oss-accelerate.aliyuncs.com",
        'bucket' => 'hkqmrool',
        'url' => 'https://hkqmrool.oss-accelerate.aliyuncs.com/'
        */
        'key' => 'LTAI5tHXDtJ9f2gRkumtncMs',
        'secret' => 'Dfgb50emba9fEAsPiFz7LT3PK7wywx' ,
        'endpoint' => 'oss-accelerate.aliyuncs.com',
        'own_endpoint'=>"hkqmapprool.oss-accelerate.aliyuncs.com",
        'bucket' => 'hkqmapprool',
        'url' => 'https://hkqmapprool.oss-accelerate.aliyuncs.com/'
    ],
    'PUBLIC_G_OSS'=>[
        /*'key' => 'LTAI5tQmPvVo3DpBMqDZa9Eu',
        'secret' => 'fqwqqZJ51MAtHhQDJVj6zGdNMt4fj4' ,
        'endpoint' => 'oss-cn-hongkong-internal.aliyuncs.com',
        'own_endpoint'=>"hkmmbkl.oss-accelerate.aliyuncs.com",
        'bucket' => 'hkmmbkl',
        'url' => 'https://hkmmbkl.oss-accelerate.aliyuncs.com/'*/
        /*
        'key' => 'LTAI5t7gVs4h3Rzgvsoqfefj',
        'secret' => 'Nzen7ucD9uDrvlpASdXQGiA87ZxkK0' ,
        'endpoint' => 'oss-cn-hongkong-internal.aliyuncs.com',
        'own_endpoint'=>"hkmmbkl2.oss-accelerate.aliyuncs.com",
        'bucket' => 'hkmmbkl2',
        'url' => 'https://hkmmbkl2.oss-accelerate.aliyuncs.com/'
        */
        'key' => 'LTAI5tHXDtJ9f2gRkumtncMs',
        'secret' => 'Dfgb50emba9fEAsPiFz7LT3PK7wywx' ,
        'endpoint' => 'oss-cn-hongkong-internal.aliyuncs.com',
        'own_endpoint'=>"hkmmbkl2.oss-accelerate.aliyuncs.com",
        'bucket' => 'hkmmbkl2',
        'url' => 'https://hkmmbkl2.oss-accelerate.aliyuncs.com/'
    ],

    'CERT_OSS'=>[
//        'key' => 'LTAI5tGoNLNdbcnLQeSHjgBq',
//        'secret' => 'VtfoTK4qhmuIE79atdV6ZG0B47IenF' ,
//        'endpoint' => 'oss-accelerate.aliyuncs.com',
//        'bucket' => 'kkiswas',
//        'own_endpoint'=>"kkiswas.oss-accelerate.aliyuncs.com",
//        'url' => 'https://kkiswas.oss-accelerate.aliyuncs.com/',
        'key' => 'LTAI5tH2gLxNXdnBa1r48NSW',
        'secret' => 'qION2kleswPHwoEQBXH6snjH90znpC' ,
        'endpoint' => 'oss-accelerate.aliyuncs.com',
        'bucket' => 'kkhhhiissz',
        'own_endpoint'=>"kkhhhiissz.oss-accelerate.aliyuncs.com",
        'url' => 'https://kkhhhiissz.oss-accelerate.aliyuncs.com/',
    ],
    'MYSQL' => [
        'host'          => '34.92.174.82',
        'port'          => '3306',
        'user'          => 'root',
        'timeout'       => '10',
        'charset'       => 'utf8mb4',
        'password'      => 'QK@e2e#sql3',
        'database'      => 'mdm',
        'POOL_MAX_NUM'  => '50',
        'POOL_MIN_NUM'  => '1',
        'POOL_TIME_OUT' => '10',
    ],
    /***Redis 配置**/
    'REDIS' => [
        'host'          => '34.96.144.207',
        'port'          => '6379',
        'auth'          => '9kE4eVFFcTtIv70M',
        'select'        =>'2',
        'POOL_MAX_NUM'  => '50',
        'POOL_MIN_NUM'  => '1',
        'POOL_TIME_OUT' => '30',
    ],
    /**数据回调地址**/
    'API_URL'=>"http://35.241.123.37:85/",

    /***googleOSS**/
    "Google_OSS"=>[
        "projectId"=>"8QyD5vQLir0QKCjIxtK8yneibFQ7y7wl+z7WOSk5",
        "bucket"=>"qkmsign",
        "cdn"=>"https://storage.googleapis.com/qkmsign/",
        "keyFile"=>"/extend/qkm-google.json",
    ],
];
