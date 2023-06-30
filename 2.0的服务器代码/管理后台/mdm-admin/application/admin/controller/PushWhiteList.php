<?php

namespace app\admin\controller;

use app\admin\model\OssConfig;
use app\common\controller\Backend;
use app\common\library\Oss;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 推送白名单管理
 *
 * @icon fa fa-circle-o
 */
class PushWhiteList extends Backend
{
    
    /**
     * PushWhiteList模型对象
     * @var \app\admin\model\PushWhiteList
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\PushWhiteList;

    }

    public function import()
    {
        parent::import();
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
        $this->request->filter(['strip_tags','trim']);
        //查询权限
        $groups = $this->auth->getGroupIds($this->auth->id);
        if ($this->request->isAjax())
        {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField'))
            {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            //$filter = $this->request->get("filter", '');
            //$filter = (array)json_decode($filter, true);
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
            foreach ($list as $row) {
                $row->getRelation('proxyuser')->visible(['username']);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                if(empty($params['user_id'])) $this->error('请选择用户');
                $whiteUser=$this->model->where("user_id",$params['user_id'])->find();
                if(!empty($whiteUser)) $this->error('已经添加该用户');
                $params["create_time"]=date('Y-m-d H:i:s');
                if ($this->model->save($params)) {
                    $this->success();
                } else {
                    $this->error('添加用户到白名单失败');
                }
            }
            $this->error('参数传递错误');
        }
        return $this->view->fetch();
    }
}
