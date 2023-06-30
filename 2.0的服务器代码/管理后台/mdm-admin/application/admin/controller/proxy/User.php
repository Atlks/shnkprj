<?php

namespace app\admin\controller\proxy;


use app\admin\model\Admin;
use app\admin\model\AdminMoneyLog;
use app\admin\model\proxy\ProxyRechargeLog;
use app\admin\model\proxy\ProxyUserMoneyNotice;
use app\common\controller\Backend;
use app\common\library\Redis;
use app\common\model\AdminMoneyLogV1;
use app\common\model\ProxyUserMoneyLogV1;
use fast\Random;
use think\Db;
use think\Exception;
use think\exception\PDOException;
use think\exception\ValidateException;


/**
 * 会员管理
 *
 * @icon fa fa-circle-o
 */
class User extends Backend
{
    
    /**
     * User模型对象
     * @var \app\admin\model\proxy\User
     */
    protected $model = null;

    protected $searchFields="username,Nickname";

    protected $multiFields="is_white,is_check,is_check_update,is_change_url_notice,is_second_pay";


    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\proxy\User();

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
                ->where($where)
                ->where('is_proxy',0)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->where('is_proxy',0)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
//            foreach ($list as $row) {
//                $count = Verified::where(['status'=>0,'user_id'=>$row['id']])->count();
//                $row['is_auth'] = $count;
//            }
            $list = collection($list)->toArray();
            $admin = Admin::where("id",$this->auth->id)->find();
            $result = array("total" => $total, "rows" => $list,'extend'=>['all_num'=>$admin["sign_num"]]);

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
                $ip = request()->ip();
                $username = $params['username'];
                if(empty($username)){
                    $this->error('请填写用户名');
                }
                $is = $this->model->where(['username'=>$username]);
                if(!empty($params['mobile'])){
                    $is->whereOr(['mobile'=>$params['mobile']]);
                }
                $is_exit = $is->value('id');
                if($is_exit){
                    $this->error('用户已存在');
                }
                $time = time();
                $salt = Random::alnum();
                $params['salt'] = $salt;
                $params['password'] = md5(md5($params['password']) . $salt);
                $params['jointime'] = $time;
                $params['logintime'] = $time;
                $params['prevtime'] = $time;
                $params['createtime'] = $time;
                $params['updatetime'] = $time;
                $params['joinip'] = $ip;
                $params['loginip'] = $ip;
                $params['is_proxy'] = 0;
                if($this->model->insert($params)){
                    $this->success('添加成功');
                }
                $this->error('添加失败');
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $daili_list  = \app\admin\model\proxy\Daili::where("is_proxy",1)
            ->where("status",'normal')
            ->order("id",'ASC')
            ->column("id,username");
        $this->assign("daili_list",$daili_list);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }

            $list = $this->model->where($pk, 'in', $ids)->select();
            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $this->model->where($pk,$v['id'])->update(['status'=>'hidden']);
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
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }


    public function smCheck($ids=''){
        $user = $this->model->where('id',$ids)->find();
        if($user){
            $this->model->where('id',$ids)->update(['authentication'=>1]);
            $this->success('认证成功');
        }
        $this->error('认证失败');
    }

    public function money_notice($ids=""){
        $info = ProxyUserMoneyNotice::get(["user_id"=>$ids]);
        $user = $this->model->where(["id"=>$ids])->find();
        if($this->request->isPost()){
            $post = $this->request->post("row/a");
            if(empty($info)){
                $data=[
                    "user_id"=>$ids,
                    "sign_num"=>$post["sign_num"],
                    "chat_id"=>$post["chat_id"],
                    "status"=>1,
                    "create_time"=>date("Y-m-d H:i:s"),
                ];
                ProxyUserMoneyNotice::create($data);
            }else{
                $data=[
                    "id"=>$info["id"],
                    "sign_num"=>$post["sign_num"],
                    "chat_id"=>$post["chat_id"],
                ];
                ProxyUserMoneyNotice::update($data);
            }
            $this->success("已加入余额提醒");
        }
        $this->view->assign("info",$info);
        $this->view->assign("user",$user);
        return $this->view->fetch();
    }

    public function pay(){
        $memo=['管理员变更金额','后台余额补差'];
        if($this->request->isPost()){
            $params = $this->request->param('row/a');
            $user =$this->model->where("id",$params["id"])->find();
            $where = [
                'id'=>$user['id'],
                'sign_num'=>$user['sign_num']
            ];
            $update_money = bcadd($user['sign_num'],$params['num'],2);
            if($update_money<0) $update_money=0;
            if(!$this->auth->isSuperAdmin()){
                $admin = Admin::where("id",$this->auth->id)->find();
                $admin_data = [
                    "create_admin_id"=>$this->auth->id,
                    "user_id"=>$user["id"],
                    "sign_num"=>$params["num"],
                    "before"=>$admin["sign_num"],
                    "create_time"=>date("Y-m-d H:i:s")
                ];
                if($params['num']>0 && $params["num"]>$admin["sign_num"]){
                    $this->error("次数不足，无法充值");
                }
                if($params["num"]>0){
                    $update_admin_money = $admin["sign_num"]-$params["num"];
                }else{
                    $money_log_where=[
                        "create_admin_id"=>$this->auth->id,
                        "user_id"=>$user["id"],
                    ];
                    if($this->auth->id==27||$this->auth->id==29){

                    }else {
                        /***变更次数为负数**/
                        $dec_num = AdminMoneyLog::where($money_log_where)
                            ->where("type", 4)
                            ->whereTime("create_time", "-3 days")
                            ->sum("sign_num");
                        $inc_num = AdminMoneyLog::where($money_log_where)
                            ->where("type", 3)
                            ->whereTime("create_time", "-3 days")
                            ->sum("sign_num");
                        $rema_num = ($inc_num - abs($dec_num));
                        if ($rema_num <= 0) {
                            $this->error("扣除次数超过3天内充值总次数，无可扣次数");
                        }
                        if ($rema_num < abs($params["num"])) {
                            $this->error("扣除次数超过3天内充值总次数，可扣除次数还剩：" . $rema_num);
                        }
                    }
                    $update_admin_money = $admin["sign_num"]+abs($params["num"]);
                }
                if($params['num']<0){
                    $admin_data["type"] = 4;
                    $admin_data["memo"] = "商务扣除";
                }else{
                    $admin_data["type"] = 3;
                    $admin_data["memo"] = "商务充值";
                }
                if($this->auth->id==27||$this->auth->id==29){
                    if($params['num']<0){
                        $admin_data["type"] = 2;
                        $admin_data["memo"] = "管理员扣除";
                    }else{
                        $admin_data["type"] = 1;
                        $admin_data["memo"] = "管理员充值";
                    }
                    $admin_data["after"] = $update_admin_money;
                    $memo_msg = $memo[$params['memo']];
                }else{
                    $admin_data["after"] = $update_admin_money;
                    $memo_msg = "代理操作";
                    $groups = $this->auth->getGroups();
                    foreach($groups as $value)
                    {
                        if($value['name'] === "管理权限")
                            $memo_msg = $memo[$params['memo']];
                    }
                }
            }else{
                $admin_data=[];
                $memo_msg=$memo[$params['memo']];
            }
            $recharge_data=[
                'user_id'=>$user['id'],
                'pid'=>$user['pid'],
                'num'=>intval($params['num']),
                'type'=>$params['num']>0?1:2,
                'status'=>1,
                'create_time'=>date('Y-m-d H:i:s'),
            ];
            $money_log_data=[
                'user_id'=>$user['id'],
                'num'=>$params['num'],
                'before'=>$user['sign_num'],
                'after'=>$update_money,
                'memo'=>$memo_msg,
                'createtime'=>time(),
                'money'=>$params['money'],
                'own'=>$params['own'],
                'univalent'=>$params['univalent'],
                'remark'=>$params['remark'],
            ];
            Db::startTrans();
            try {
                Db::table("proxy_user")->where($where)->update(['sign_num'=>$update_money]);
                Db::table("proxy_user_money_log")->insert($money_log_data);
                Db::table("proxy_recharge_log")->insert($recharge_data);
                if(!empty($admin_data)){
                    AdminMoneyLog::create($admin_data);
                    Admin::update(["id"=>$this->auth->id,"sign_num"=>$update_admin_money]);
                }
                Db::commit();
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            /**清除缓存**/
            $redis = new Redis(["select"=>4]);
            $redis->handle()->del("user_userId:".trim($user["id"]));
            $redis->handle()->close();
            $redis = null;

            $this->success('充值成功');

//            if($this->model->where($where)->update(['sign_num'=>$update_money])){
//                $recharge_data=[
//                    'user_id'=>$user['id'],
//                    'pid'=>$user['pid'],
//                    'num'=>intval($params['num']),
//                    'type'=>$params['num']>0?1:2,
//                    'status'=>1,
//                    'create_time'=>date('Y-m-d H:i:s'),
//                ];
//                $money_log_data=[
//                    'user_id'=>$user['id'],
//                    'num'=>$params['num'],
//                    'before'=>$user['sign_num'],
//                    'after'=>$update_money,
//                    'memo'=>$memo_msg,
//                    'createtime'=>time(),
//                    'money'=>$params['money'],
//                    'own'=>$params['own'],
//                    'univalent'=>$params['univalent'],
//                    'remark'=>$params['remark'],
//                ];
//                \app\admin\model\proxy\UserMoneyLog::create($money_log_data);
//                ProxyRechargeLog::create($recharge_data);
//                if(!empty($admin_data)){
//                    AdminMoneyLog::create($admin_data);
//                    Admin::update(["id"=>$this->auth->id,"sign_num"=>$update_admin_money]);
//                }
//                /**清除缓存**/
//                $redis = new Redis(["select"=>4]);
//                $redis->handle()->del("user_userId:".trim($user["id"]));
//                $redis->handle()->close();
//                $redis = null;
//
//                $this->success('充值成功');
//            }
//            $this->error('充值失败');
        }
        $id = $this->request->param('ids');
        $user =$this->model->where("id",$id)->find();
        $this->assign('user',$user);
        $this->assign('id',$id);
        $this->assign('memo',$memo);
        return $this->view->fetch();
    }


    public function v1_pay(){
        $memo=['管理员变更金额','后台余额补差'];
        if($this->request->isPost()){
            $params = $this->request->param('row/a');
            $user =$this->model->where("id",$params["id"])->find();
            $where = [
                'id'=>$user['id'],
                'v1_num'=>$user['v1_num']
            ];
            $update_money = bcadd($user['v1_num'],$params['num'],2);
            if($update_money<0) $update_money=0;
            if(!$this->auth->isSuperAdmin()){
                $admin = Admin::where("id",$this->auth->id)->find();
                $admin_data = [
                    "create_admin_id"=>$this->auth->id,
                    "user_id"=>$user["id"],
                    "sign_num"=>$params["num"],
                    "before"=>$admin["sign_num"],
                    "create_time"=>date("Y-m-d H:i:s")
                ];
                if($params['num']>0 && $params["num"]>$admin["sign_num"]){
                    $this->error("次数不足，无法充值");
                }
                if($params["num"]>0){
                    $update_admin_money = $admin["sign_num"]-$params["num"];
                }else{
                    $money_log_where=[
                        "create_admin_id"=>$this->auth->id,
                        "user_id"=>$user["id"],
                    ];
                    /***变更次数为负数**/
                    $dec_num = AdminMoneyLogV1::where($money_log_where)
                        ->where("type",4)
                        ->whereTime("create_time","-3 days")
                        ->sum("sign_num");
                    $inc_num = AdminMoneyLogV1::where($money_log_where)
                        ->where("type",3)
                        ->whereTime("create_time","-3 days")
                        ->sum("sign_num");
                    $rema_num = ($inc_num-abs($dec_num));
                    if($rema_num<=0){
                        $this->error("扣除次数超过3天内充值总次数，无可扣次数");
                    }
                    if($rema_num<abs($params["num"])){
                        $this->error("扣除次数超过3天内充值总次数，可扣除次数还剩：".$rema_num);
                    }
                    $update_admin_money = $admin["sign_num"]+abs($params["num"]);
                }
                if($params['num']<0){
                    $admin_data["type"] = 4;
                    $admin_data["memo"] = "商务扣除";
                }else{
                    $admin_data["type"] = 3;
                    $admin_data["memo"] = "商务充值";
                }
                $admin_data["after"] = $update_admin_money;
                $memo_msg = "代理操作";
            }else{
                $admin_data=[];
                $memo_msg=$memo[$params['memo']];
            }
            if($this->model->where($where)->update(['v1_num'=>$update_money])){
                $money_log_data=[
                    'user_id'=>$user['id'],
                    'num'=>$params['num'],
                    'before'=>$user['v1_num'],
                    'after'=>$update_money,
                    'memo'=>$memo_msg,
                    'createtime'=>time(),
                    'money'=>$params['money'],
                    'own'=>$params['own'],
                    'univalent'=>$params['univalent'],
                    'remark'=>$params['remark'],
                ];
                ProxyUserMoneyLogV1::create($money_log_data);
                if(!empty($admin_data)){
                    AdminMoneyLogV1::create($admin_data);
                    Admin::update(["id"=>$this->auth->id,"sign_num"=>$update_admin_money]);
                }
                /**清除缓存**/
                $redis = new Redis(["select"=>4]);
                $redis->handle()->del("user_userId:".trim($user["id"]));
                $redis->handle()->close();
                $redis = null;
                $this->success('充值成功');
            }
            $this->error('充值失败');
        }
        $id = $this->request->param('ids');
        $user =$this->model->where("id",$id)->find();
        $this->assign('user',$user);
        $this->assign('id',$id);
        $this->assign('memo',$memo);
        return $this->view->fetch();
    }

    public function select_account($ids){
        $user =$this->model->where("id",$ids)->find();
        if($this->request->isPost()){
            $params = $this->request->param('row/a');
            $account_id = $params["account_id"];
            if($this->model->where("id",$ids)->update(['account_id'=>$account_id])){
                /**清除缓存**/
                $redis = new Redis(["select"=>4]);
                $redis->handle()->del("user_userId:".trim($ids));
                $redis->handle()->close();
                $redis = null;
                $this->success('更新成功');
            }else{
                $this->error('更新失败，请稍后再试');
            }
        }
        $account_list = (new \app\admin\model\Enterprise())->column("id,name");
        $this->assign("account_list",$account_list);
        $this->assign('user',$user);
        return $this->view->fetch();
    }
    

}
