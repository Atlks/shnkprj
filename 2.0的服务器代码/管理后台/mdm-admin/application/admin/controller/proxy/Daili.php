<?php

namespace app\admin\controller\proxy;

use app\admin\model\Admin;
use app\admin\model\AdminMoneyLog;
use app\admin\model\OssConfig;
use app\admin\model\proxy\ProxyRechargeLog;
use app\admin\model\proxy\User;
use app\admin\model\ProxyUserDomain;
use app\admin\model\ProxyUserDomainHistory;
use app\common\controller\Backend;
use app\common\library\Oss;
use app\common\model\Config;
use think\Db;
use fast\Random;
use think\Exception;
use think\exception\PDOException;
use app\admin\model\proxy\Style as StyleModel;
use think\exception\ValidateException;

/**
 * 会员管理
 *
 * @icon fa fa-circle-o
 */
class Daili extends Backend
{
    
    /**
     * Daili模型对象
     * @var \app\admin\model\proxy\Daili
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\proxy\Daili;

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
                    ->with(['proxyuserdomain'])
                    ->where($where)
                    ->where('is_proxy',1)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->with(['proxyuserdomain'])
                    ->where($where)
                    ->where('is_proxy',1)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                
                $row->getRelation('proxyuserdomain')->visible(['domain','cert_path','pem_path','key_path','logo','logo_name']);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }
    /**
     * 新增
     * @return string
     * @throws \think\Exception
     */
    public function add()
    {
        if($this->request->isPost()){
            $params = $this->request->post("row/a");
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
            $params['is_proxy'] = 1;
            if($this->model->insert($params)){
                $this->success('添加成功');
            }
            $this->error('添加失败');
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = NULL)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    $result = $row->allowField(true)->save($params);
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
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    public function domain($ids=''){
        if($this->request->isPost()){
            $post = $this->request->post('row/a');
            if(!strpos($post['cert_path'],'.crt')){
                $this->error('请上传CRT证书');
            }
            if(!strpos($post['pem_path'],'.pem')){
                $this->error('请上传PEM证书');
            }
            if(!strpos($post['key_path'],'.key')){
                $this->error('请上传KEY证书');
            }
            $cert_path = 'dailicert/'.date('Y-m-d').'/'.pathinfo($post['cert_path'])['basename'];
            $pem_path = 'dailicert/'.date('Y-m-d').'/'.pathinfo($post['pem_path'])['basename'];
            $key_path = 'dailicert/'.date('Y-m-d').'/'.pathinfo($post['key_path'])['basename'];
            $oss_config=OssConfig::where("name","g_oss")
                ->where("status",1)
                ->cache(true,10*60)
                ->find();
            $oss = new Oss($oss_config);
            $data = [
                'user_id'=>$post['user_id'],
                'domain'=>$post['domain'],
                'download_url'=>$post['download_url'],
                'logo_name'=>$post['logo_name'],
                'create_time'=>date('Y-m-d H:i:s'),
                'qq'=>$post['qq'],
                'skype'=>$post['skype'],
                'telegram'=>$post['telegram'],
                'style_id'=>$post['style_id'],
                'wx1_host'=>$post['wx1_host'],
                'wx2_host'=>$post['wx2_host'],
                'oss_id'=>$post['oss_id'],
                'ssl_sign_id'=>$post['ssl_sign_id'],
                'chat_id'=>$post['chat_id'],
            ];
            if(isset($post["callback_key"])){
                $data["callback_key"]=$post["callback_key"];
            }else{
                $data["callback_key"]="";
            }
            if(is_file(ROOT_PATH.'/public'.$post['cert_path'])){
                if(!$oss->ossUpload(ROOT_PATH.'/public'.$post['cert_path'],$cert_path)){
                    $this->error('添加失败');
                }
                $data['cert_path']=$cert_path;
            }
            if(is_file(ROOT_PATH.'/public'.$post['pem_path'])){
                if(!$oss->ossUpload(ROOT_PATH.'/public'.$post['pem_path'],$pem_path)){
                    $this->error('添加失败');
                }
                $data['pem_path']=$pem_path;
            }
            if(is_file(ROOT_PATH.'/public'.$post['key_path'])){
                if(!$oss->ossUpload(ROOT_PATH.'/public'.$post['key_path'],$key_path)){
                    $this->error('添加失败');
                }
                $data['key_path']=$key_path;
            }
            if(is_file(ROOT_PATH.'/public'.$post['logo'])){
                if($oss->ossUpload(ROOT_PATH.'/public'.$post['logo'],substr($post['logo'],1))){
                    $post['logo']= substr($post['logo'],1);
                }else{
                    $this->error('添加失败');
                }
                $data['logo']=$post['logo'];
            }
            $is_exit = ProxyUserDomain::get(['user_id'=>$post['user_id']]);
            $historydata = [
                'user_id'=>$post['user_id'],
                'domain'=>$post['domain'],
                'download_url'=>$post['download_url'],
                'create_time'=>date('Y-m-d H:i:s'),
                'wx1_host'=>$post['wx1_host'],
                'wx2_host'=>$post['wx2_host'],
            ];
            if($is_exit){
                $data['id'] = $is_exit['id'];
                ProxyUserDomain::update($data);
                ProxyUserDomainHistory::create($historydata);
                if($post['download_url']!=$is_exit["download_url"]||$post['wx1_host']!=$is_exit["wx1_host"]){
                    $notice_data=[
                        "pid"=>$post['user_id']
                    ];
                    if($post['download_url']!=$is_exit["download_url"]){
                        $notice_data["download_url"] = $post["download_url"];
                    }else{
                        $notice_data["download_url"] ="";
                    }
                    if($post['wx1_host']!=$is_exit["wx1_host"]){
                        $notice_data["wx_url"] = $post["wx1_host"];
                    }else{
                        $notice_data["wx_url"] ="";
                    }
                    $re = $this->http_request("http://35.241.123.37:85/api/notice_proxy_user",$notice_data);
                }
            }else{
                $data['create_time'] = date('Y-m-d H:i:s');
                ProxyUserDomain::create($data);
                ProxyUserDomainHistory::create($historydata);
            }
            $this->success('设置成功');
        }
        $user = $this->model->where('id',$ids)->find();
        $domain = ProxyUserDomain::get(['user_id'=>$ids]);
        $style_id = StyleModel::where('status',1)
            ->where("type",20)
            ->order('is_default','desc')
            ->column('id,name');
        $oss_config = OssConfig::where("status",1)->column("id,nickname");
        $callback_key_list = Config::where("name",'LIKE',"callback_url%")->column("name,value");
        $this->assign('row',$user);
        $this->assign('domain',$domain);
        $this->assign('style_id',$style_id);
        $this->assign('oss_config',$oss_config);
        $this->assign('callback_key_list',$callback_key_list);
        return $this->view->fetch();
    }

    public function pay(){
        $memo=['管理员变更金额','后台余额补差'];
        if($this->request->isPost()){
            $params = $this->request->param('row/a');
            $user =$this->model->where("id",$params["id"])->find();
            if(!$this->auth->isSuperAdmin()){
              //  $this->error("暂无权限");
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
                    $dec_num = AdminMoneyLog::where($money_log_where)
                        ->where("type",4)
                        ->whereTime("create_time","-3 days")
                        ->sum("sign_num");
                    $inc_num = AdminMoneyLog::where($money_log_where)
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
                $groups = $this->auth->getGroups();
                    foreach($groups as $value)
                    {
                        if($value['name'] === "管理权限")
                            $memo_msg = $memo[$params['memo']];
                    }
                $memo_msg = "代理操作";

            }else{
                $admin_data=[];
                $memo_msg=$memo[$params['memo']];
            }
           // $user = User::get($params['id']);
            $where = [
                'id'=>$user['id'],
                'sign_num'=>$user['sign_num']
            ];
            $update_money = bcadd($user['sign_num'],$params['num'],2);
            if($update_money<0) $update_money=0;
            if($this->model->where($where)->update(['sign_num'=>$update_money])){
                $recharge_data=[
                    'user_id'=>$user['id'],
                    'pid'=>$user['id'],
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
                \app\admin\model\proxy\UserMoneyLog::create($money_log_data);
                ProxyRechargeLog::create($recharge_data);
                if(!empty($admin_data)){
                    AdminMoneyLog::create($admin_data);
                    Admin::update(["id"=>$this->auth->id,"sign_num"=>$update_admin_money]);
                }
                $this->success('充值成功');
            }
            $this->error('充值失败');
        }
        $id = $this->request->param('ids');
        $user = User::get($id);
        $this->assign('user',$user);
        $this->assign('id',$id);
        $this->assign('memo',$memo);
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
                    User::where('pid',$v['id'])->update(['status'=>'hidden']);
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


    public function oss_config()
    {
        $post = $this->request->param();
        $pageNumber = $post['pageNumber'] ?? 1;
        $pageSize = $post['pageSize'] ?? 10;
        $orderby = $post["orderBy"];
        $where = [
            'status' => 1,
        ];
        if (!empty($post['name'])) {
            $where['nickname'] = ['LIKE', '%' . $post['name'] . '%'];
        }
        if (!empty($post['keyValue'])) {
            $where['id'] = $post["keyValue"];
        }
        $mode = new OssConfig();
        $offset = (intval($pageNumber) - 1) * $pageSize;
        $total = $mode->where($where)->count('id');
        $list = $mode->where($where)
            ->order($orderby[0][0], $orderby[0][1])
            ->limit($offset, $pageSize)
            ->column('id,nickname,bucket');
        foreach ($list as $k => $v) {
            $list[$k]['name'] = $v['nickname'];
        }
        $list[] = [
            "id"=>0,
            "name"=>"无分流库"
        ];
        return json(['list' => array_values($list), 'total' => $total+1]);
    }

    public function tb_oss($ids){
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->success("该功能已暂时关闭");
//        $proxy = ProxyUserDomain::where("user_id",$ids)->find();
//        if(empty($proxy)||$proxy["oss_id"]==0){
//            $this->error("该代理线不存在分流库");
//        }
//        $oss_config = OssConfig::where("id",$proxy["oss_id"])
//            ->where("status",1)
//            ->find();
//        if(empty($oss_config)){
//            $this->error("该代理线OSS分流库不存在");
//        }
//        /**分流到深圳同步**/
//        $oss_config["endpoint"] = "oss-cn-shenzhen-internal.aliyuncs.com";
//        $post_data = [
//            "proxy_id"=>$ids,
//            "oss_id"=>$proxy["oss_id"],
//            "async_oss_config"=>$oss_config
//        ];
//        $result = $this->http_request("http://35.241.123.37:85/api/proxy_async_oss",$post_data);
//        $result_async = json_decode($result,true);
//        if (empty($result_async["code"]) || $result_async["code"] != 200) {
//            $this->error("任务提交失败，请稍后再试", $result_async);
//        }else{
//            $this->success("批量同步任务已提交");
//        }
    }


}
