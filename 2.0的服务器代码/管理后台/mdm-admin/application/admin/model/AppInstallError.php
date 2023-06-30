<?php

namespace app\admin\model;

use think\Model;


class AppInstallError extends Model
{

    

    

    // 表名
    protected $table = 'app_install_error';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







    public function proxyapp()
    {
        return $this->belongsTo('ProxyApp', 'tag', 'tag', [], 'LEFT')->setEagerlyType(0);
    }
}
