<?php

namespace app\admin\model;

use think\Model;


class DomainList extends Model
{

    

    

    // 表名
    protected $table = 'domain_list';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







    public function admin()
    {
        return $this->belongsTo('Admin', 'admin_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function proxyuserdomain()
    {
        return $this->belongsTo('ProxyUserDomain', 'daili_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
