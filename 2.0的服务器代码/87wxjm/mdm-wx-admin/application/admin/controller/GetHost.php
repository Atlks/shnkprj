<?php

namespace app\admin\controller;

use app\admin\library\Auth;
use app\admin\model\Admin;
use app\common\controller\Backend;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 微信域名管理
 *
 * @icon fa fa-circle-o
 */
class GetHost extends Backend
{

    /**
     * GetHost模型对象
     * @var \app\admin\model\GetHost
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\GetHost;

    }

    /**
     * 查看
     */
    public function index()
    {
        $id = $this->auth->id;
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
                ->with(['admin'])
                ->where($where)
                ->where("wx_host.user_id",$id)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {

                $row->getRelation('admin')->visible(['username']);
            }

            $info = Admin::where("id",$id)->find();
            $result = array("total" => $list->total(), "rows" => $list->items(),'extend'=>['num'=>$info["use_num"]]);

            return json($result);
        }
        return $this->view->fetch();
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    /**
     * 批量更新
     */
    public function multi($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $action = $this->request->param("action");
        if($action ==="get_url"){
            return $this->get_url();
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        if ($ids) {
            if ($this->request->has('params')) {
                parse_str($this->request->post("params"), $values);
                $values = $this->auth->isSuperAdmin() ? $values : array_intersect_key($values, array_flip(is_array($this->multiFields) ? $this->multiFields : explode(',', $this->multiFields)));
                if ($values) {
                    $adminIds = $this->getDataLimitAdminIds();
                    if (is_array($adminIds)) {
                        $this->model->where($this->dataLimitField, 'in', $adminIds);
                    }
                    $count = 0;
                    Db::startTrans();
                    try {
                        $list = $this->model->where($this->model->getPk(), 'in', $ids)->select();
                        foreach ($list as $index => $item) {
                            $count += $item->allowField(true)->isUpdate(true)->save($values);
                        }
                        Db::commit();
                    } catch (PDOException $e) {
                        Db::rollback();
                        $this->error($e->getMessage());
                    } catch (Exception $e) {
                        Db::rollback();
                        $this->error($e->getMessage());
                    }
                    if ($count) {
                        $this->success();
                    } else {
                        $this->error(__('No rows were updated'));
                    }
                } else {
                    $this->error(__('You have no permission'));
                }
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }


    public function get_url(){
        $id= $this->auth->id;
        if(!$this->auth->isSuperAdmin()){
           $is_dec=true;
            $info = Admin::where("id",$id)->find();
            if($info["use_num"]<=0){
                $this->error("暂无次数可用,请充值");
            }
        }else{
            $is_dec=false;
        }

        $url = \app\admin\model\WxHost::where("status",1)->where("user_id",0)->find();
        if(empty($url)){
            $this->error("无可用域名，请联系商务");
        }
        $update=[
            "user_id"=>$id,
            "use_time"=>date("Y-m-d H:i:s"),
        ];
        Db::startTrans();
        try {
            $result = $this->model->where("id",$url["id"])->update($update);
            if($is_dec) {
                Db::table("admin")->where("id", $id)->setDec("use_num", 1);
            }
            Db::commit();
        } catch (ValidateException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (PDOException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result !== false) {
            $this->success();
        } else {
            $this->error(__('No rows were inserted'));
        }
    }


}
