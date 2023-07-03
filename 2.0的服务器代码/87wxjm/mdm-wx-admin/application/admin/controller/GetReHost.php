<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 301 跳转
 *
 * @icon fa fa-circle-o
 */
class GetReHost extends Backend
{

    /**
     * GetReHost模型对象
     * @var \app\admin\model\GetReHost
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\GetReHost;

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

            $id = $this->auth->id;
            $list = $this->model
                ->where($where)
                ->where("user_id",$id)
                ->order($sort, $order)
                ->paginate($limit);
            $info = Admin::where("id",$id)->find();
            $result = array("total" => $list->total(), "rows" => $list->items(),'extend'=>['num'=>$info["use_num"]]);

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

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $id= $this->auth->id;
                if(!$this->auth->isSuperAdmin()){
                    $info = Admin::where("id",$id)->find();
                    if($info["use_num"]<=0){
                        $this->error("暂无次数可用,请充值");
                    }
                }
                $url = \app\admin\model\ReHost::where("user_id",0)->find();
                if(empty($url)){
                    $this->error("无可用域名，请联系商务");
                }
                $update=[
                    "re_url"=>$params['re_url'],
                    "remark"=>$params['remark'],
                    "user_id"=>$id,
                    "use_time"=>date("Y-m-d H:i:s"),
                ];
                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model->where("id",$url["id"])->update($update);
                    Db::table("admin")->where("id", $id)->setDec("use_num", 1);
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
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }


}
