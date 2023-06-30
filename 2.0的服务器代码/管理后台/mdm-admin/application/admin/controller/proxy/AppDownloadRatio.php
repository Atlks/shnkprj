<?php

namespace app\admin\controller\proxy;

use app\common\controller\Backend;
use think\Db;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class AppDownloadRatio extends Backend
{
    
    /**
     * AppDownloadRatio模型对象
     * @var \app\admin\model\proxy\AppDownloadRatio
     */
    protected $model = null;

    protected $searchFields="proxyuser.username";

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\proxy\AppDownloadRatio;

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
                    ->with(['proxyuser'])
                    ->where($where)
                    ->field("count(*) as app_num")
                    ->group("app_download_ratio.user_id")
                    ->order("app_num",$order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $pid = $row['proxyuser']["pid"];
                $user_id = $row["user_id"];
                $row->visible(['id','name','user_id','icon','status','is_delete',"app_num"]);
                $row->visible(['proxyuser']);
				$row->getRelation('proxyuser')->visible(['username',"pid"]);
                $bale_rate_table = getTable("proxy_bale_rate",$pid);
                $row->download_num= Db::table($bale_rate_table)->where("user_id",$user_id)->count("id");
                $row->visible(["download_num"]);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

}
