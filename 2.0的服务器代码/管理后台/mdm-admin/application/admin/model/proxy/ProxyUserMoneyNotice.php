<?php

namespace app\admin\model\proxy;

use think\Model;


class ProxyUserMoneyNotice extends Model
{

    

    

    // 表名
    protected $table = 'proxy_user_money_notice';
    
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
        return $this->belongsTo('app\admin\model\ProxyUser', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
