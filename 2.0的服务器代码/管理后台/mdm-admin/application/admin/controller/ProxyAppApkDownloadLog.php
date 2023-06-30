<?php

namespace app\admin\controller;

use app\common\controller\Backend;

/**
 * 安卓下载记录
 *
 * @icon fa fa-circle-o
 */
class ProxyAppApkDownloadLog extends Backend
{
    
    /**
     * ProxyAppApkDownloadLog模型对象
     * @var \app\admin\model\ProxyAppApkDownloadLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\ProxyAppApkDownloadLog;

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
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->where($where)
                ->whereTime("create_time","d")
                ->group("app_id")
                ->order($sort, $order)
                ->field("id,app_id,user_id,count(id) as num")
                ->paginate($limit);
            $today_num = $this->model
                ->where($where)
                ->whereTime("create_time","d")
                ->count("id");

            $yesterday_num = $this->model
                ->where($where)
                ->whereTime("create_time","yesterday")
                ->count("id");
            $week_num = $this->model
                ->where($where)
                ->whereTime("create_time","-7 days")
                ->count("id");
            $row = $list->items();
            $app = new \app\admin\model\ProxyApp();
            foreach ($row as $k=>$v){
                $row[$k]["app_name"] = $app->where("id",$v["app_id"])->cache(true,600)->value("name");
            }

            $result = array("total" => $list->total(), "rows" => $row,'extend'=>["today_num"=>$today_num,"yesterday_num"=>$yesterday_num,"week_num"=>$week_num]);

            return json($result);
        }
        return $this->view->fetch();
    }


}
