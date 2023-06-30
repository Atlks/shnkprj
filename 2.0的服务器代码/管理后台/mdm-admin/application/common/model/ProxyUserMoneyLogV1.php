<?php

namespace app\common\model;

use think\Model;


class ProxyUserMoneyLogV1 extends Model
{

    

    

    // 表名
    protected $table = 'proxy_user_money_log_v1';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
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
