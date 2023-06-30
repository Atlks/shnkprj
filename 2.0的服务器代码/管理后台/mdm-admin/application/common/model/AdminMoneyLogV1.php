<?php

namespace app\common\model;

use think\Model;


class AdminMoneyLogV1 extends Model
{

    

    

    // 表名
    protected $table = 'admin_money_log_v1';
    
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
        return $this->belongsTo('Admin', 'create_admin_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
