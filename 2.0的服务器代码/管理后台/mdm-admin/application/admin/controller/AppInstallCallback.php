<?php

namespace app\admin\controller;

use app\common\controller\Backend;

/**
 * APP安装回调
 *
 * @icon fa fa-circle-o
 */
class AppInstallCallback extends Backend
{
    
    /**
     * AppInstallCallback模型对象
     * @var \app\admin\model\AppInstallCallback
     */
    protected $model = null;

    protected $searchFields="proxyapp.name,proxyuser.username";

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\AppInstallCallback;

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
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                    ->with(['proxyapp','proxyuser'])
                    ->where($where)
                    ->order("install_num", "DESC")
                ->field("count(*) as install_num")
                ->group("app_install_callback.app_id")
                    ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('proxyapp')->visible(['name']);
				$row->getRelation('proxyuser')->visible(['username']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

}
