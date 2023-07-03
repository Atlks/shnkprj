<?php

namespace app\admin\model;

use think\Model;


class AdminMoneyLog extends Model
{

    

    

    // 表名
    protected $table = 'admin_money_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







    public function admin()
    {
        return $this->belongsTo('Admin', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
