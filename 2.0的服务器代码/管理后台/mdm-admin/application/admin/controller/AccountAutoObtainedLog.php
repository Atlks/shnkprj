<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\admin\model\Account;

/**
 * 账号自动下架管理
 *
 * @icon fa fa-circle-o
 */
class AccountAutoObtainedLog extends Backend
{
    
    /**
     * AccountAutoObtainedLog模型对象
     * @var \app\admin\model\AccountAutoObtainedLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\AccountAutoObtainedLog;

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
            $op = (array)json_decode($this->request->param("op"),true);
            $is_time=false;
            if(empty($op)||!array_key_exists("create_time",$op)){
                $is_time = true;
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            if($is_time){
                $total = $this->model
                    ->with(['account'])
                    ->where($where)
                    ->whereTime("account_auto_obtained_log.create_time",'d')
                    ->order($sort, $order)
                    ->count();

                $list = $this->model
                    ->with(['account'])
                    ->where($where)
                    ->whereTime("account_auto_obtained_log.create_time",'d')
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();
            }else{
                $total = $this->model
                    ->with(['account'])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

                $list = $this->model
                    ->with(['account'])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();
            }


            foreach ($list as $row) {
                $row->visible(['id','account_id','account','msg','create_time']);
                $row->visible(['account']);
				$row->getRelation('account')->visible(['account']);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    public function revoke($ids=''){
        if(empty($ids)){
            $this->error("请选择撤回数据");
        }
        $account_id = $this->model->whereIn('id',$ids)->column("account_id");
        if(empty($account_id)){
            $this->success("撤回0条数据");
        }
        $Account = new Account();
        $Account->whereIn('id',$account_id)->update(["status"=>1]);
        $this->model->whereIn('id',$ids)->delete();
        $this->success('撤回成功');
    }

}
