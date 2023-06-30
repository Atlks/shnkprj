<?php

namespace app\admin\model;

use think\Model;


class ProxyApp extends Model
{

    

    

    // 表名
    protected $table = 'proxy_app';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







    public function proxyuser()
    {
        return $this->belongsTo('ProxyUser', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
