<?php

namespace app\admin\controller\proxy;

use app\common\controller\Backend;

/**
 * 会员余额变动管理
 *
 * @icon fa fa-circle-o
 */
class UserMoneyLog extends Backend
{
    
    /**
     * UserMoneyLog模型对象
     * @var \app\admin\model\proxy\UserMoneyLog
     */
    protected $model = null;

    protected $searchFields='proxyuser.username,memo';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\proxy\UserMoneyLog;

    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax())
        {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField'))
            {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    ->with(['proxyuser'])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->with(['proxyuser'])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();
            $num = $this->model
                ->with(['proxyuser'])
                ->where($where)
                ->order($sort, $order)
                ->sum('user_money_log.num');
            $money = $this->model
                ->with(['proxyuser'])
                ->where($where)
                ->order($sort, $order)
                ->sum('user_money_log.money');
            foreach ($list as $row) {
               // $row->createtime = date("Y-m-d H:i:s",intval($row->createtime));
                $row->num = intval($row->num);
                $row->before = intval($row->before);
                $row->after = intval($row->after);
                $row->type_text = $row->before>$row->after?"扣除":"充值";
                $row->getRelation('proxyuser')->visible(['username',"pid"]);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list,"extend"=>['num'=>intval($num),'money'=>$money]);

            return json($result);
        }
        return $this->view->fetch();
    }
}
