<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use fast\Random;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 商务使用域名列管理
 *
 * @icon fa fa-circle-o
 */
class DomainList extends Backend
{
    
    /**
     * DomainList模型对象
     * @var \app\admin\model\DomainList
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\DomainList;

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
            /**商务对账***/
            if($this->auth->isSuperAdmin()||$this->auth->id=="10"){
                $admin_where=[];
            }else{
                $admin_where=[
                    "domain_list.admin_id"=>$this->auth->id
                ];
            }
            $list = $this->model
                    ->with(['admin','proxyuserdomain'])
                    ->where($where)
                    ->where($admin_where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('admin')->visible(['username']);
				$row->getRelation('proxyuserdomain')->visible(['domain']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        $groups = $this->auth->getGroupIds($this->auth->id);
        if(in_array(1,$groups)){
            $visable = true;
            $is_operate = "=";
        }else{
            $visable = false;
            $is_operate = false;
        }
        $this->assignconfig("is_showColumn",$visable);
        $this->assignconfig("is_operate",$is_operate);
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
                if($params["is_fan"]==1){
                    $i=0;
                    $num  = $params["num"];
                    $mode = new \app\admin\model\DomainList();
                    $data = [];
                    while (true){
                        $str = Random::alpha(rand(1,5));
                        $host = strtolower($str).".".$params["domain"];
                        $is_exit = $mode->where("domain",$host)
                            ->find();
                        if($is_exit){
                           continue;
                        }else{
                            $data[]=[
                                "domain"=>$host,
                                "status"=>$params["status"],
                                "create_time"=> date("Y-m-d H:i:s"),
                            ];
                            $i++;
                        }
                        if($i>=$num){
                            break;
                        }
                    }
                }else{
                    $data[]=[
                        "domain"=>$params["domain"],
                        "status"=>$params["status"],
                        "create_time"=> date("Y-m-d H:i:s"),
                    ];
                    $is_exit = \app\admin\model\DomainList::where("domain",$params["domain"])->find();
                    if($is_exit){
                        $this->error("域名已经存在");
                    }
                }
//                $params["create_time"] = date("Y-m-d H:i:s");
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $result = $this->model->allowField(true)->saveAll($data);
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

    public function get_domain(){
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                if($params["is_fan"]==1){
                    $host = $params["prefix"].".".$params["domain_url"];
                    $info = $this->model->where("domain",$host)
                        ->find();
                    if(!empty($info)){
                        $this->error("该域名已存在，请重新填写");
                    }
                    $data = [
                        "domain"=>$host,
                        "status"=>1,
                        "create_time"=> date("Y-m-d H:i:s"),
                        "is_use"=>1,
                        "admin_id"=>$this->auth->id,
                        "update_time"=>date("Y-m-d H:i:s"),
                        "use_time"=>date("Y-m-d H:i:s"),
                    ];
                    if(\app\admin\model\DomainList::create($data)){
                        $this->success("域名使用成功");
                    }else{
                        $this->error("域名使用失败，请重新获取");
                    }
                }else{
                    $info = $this->model->where("id",$params["id"])
                        ->where("status",1)
                        ->where("is_use",0)
                        ->find();
                    if(empty($info)){
                        $this->error("该域名已被使用，请重新获取");
                    }
                    $update = [
                        "id"=>$params["id"],
                        "is_use"=>1,
                        "admin_id"=>$this->auth->id,
                        "update_time"=>date("Y-m-d H:i:s"),
                        "use_time"=>date("Y-m-d H:i:s"),
                    ];
                    if(\app\admin\model\DomainList::update($update)){
                        $this->success("域名使用成功");
                    }else{
                        $this->error("域名使用失败，请重新获取");
                    }
                }
            }
        }
        $list = \app\admin\model\DomainMainList::where("status",1)
            ->column("*");
       if(empty($list)){
           $this->error("暂无可用域名");
       }
        $row = $list[array_rand($list)];
       $this->assign("row",$row);
        return $this->view->fetch();
    }

    public function get_url_list(){
        $params = $this->request->param();
       if($params["is_fan"]==1){
           $list = \app\admin\model\DomainMainList::where("status",1)
               ->column("*");
           if(empty($list)){
               return json(["code"=>0,"msg"=>"暂无可用域名"]);
           }
           $row = $list[array_rand($list)];
           return  json(["code"=>1,"msg"=>'success',"data"=>$row]);
       }else{
           $list = $this->model->where("status",1)
               ->where("is_use",0)
               ->column("*");
           if(empty($list)){
               return json(["code"=>0,"msg"=>"暂无可用域名"]);
           }
           $row = $list[array_rand($list)];
           return  json(["code"=>1,"msg"=>'success',"data"=>$row]);
       }
    }


}
