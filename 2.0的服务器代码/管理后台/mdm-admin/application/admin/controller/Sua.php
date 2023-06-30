<?php


namespace app\admin\controller;


use app\admin\model\proxy\User;
use app\admin\model\ProxyDownloadCodeList;
use app\admin\model\UdidList;
use app\common\controller\Backend;
use app\admin\model\ProxyApp;
use think\Db;

class Sua extends Backend
{
    protected $model;

    public function _initialize()
    {
        parent::_initialize();
    }

    public function index()
    {
        if($this->request->isPost()){
            $post = $this->request->post("row/a");
            if($post['cate']==2){
                if(empty($post["udid_num"])||empty($post["app_id"])){
                    $this->error('请选择数据');
                }
                /**
                 * 下载限制
                 */
                $is_code = ProxyDownloadCodeList::where([
                    'app_id'=>$post["app_id"],
                    'status'=>1
                ])->find();
                if($is_code){
                    $this->error('应用有下载码');
                }
                $is_white = \app\admin\model\AppWhitelist::where([
                    'app_id'=>$post["app_id"],
                    'status'=>1
                ])->find();
                if($is_white){
                    $this->error('应用在白名单');
                }
                return $this->appAddCord($post["udid_num"],$post["app_id"]);
            }elseif($post['cate']==3){
                if(empty($post["user"])||empty($post["times"])){
                    $this->error('请选择数据');
                }
                return $this->add_user($post["user"],$post["times"]);
//                $this->error("暂未开放");
            }else{
                if (empty($post['num'])) {
                    $this->error('请输入下载数量');
                }
                $num = $this->allDownloadlist($post['num']);
                $this->success('添加成功,共添加记录' . $num . '条');
            }
        }
        return $this->view->fetch();
    }

    public function app()
    {
        $post = $this->request->param();
        $pageNumber = $post['pageNumber'] ?? 1;
        $pageSize = $post['pageSize'] ?? 10;
        $orderby = $post["orderBy"];
        $where = [
            'status' => 1,
            'is_delete' => 1,
            'is_download' => 0,
        ];
        if (!empty($post['name'])) {
            $where['name'] = ['LIKE', '%' . $post['name'] . '%'];
        }
        $mode = new ProxyApp();
        $offset = (intval($pageNumber) - 1) * $pageSize;
        $total = $mode->where($where)->count('id');
        $list = $mode->where($where)
            ->order($orderby[0][0], $orderby[0][1])
            ->limit($offset, $pageSize)
            ->column('id,name,tag,download_num');
        foreach ($list as $k => $v) {
            $list[$k]['name'] = $v['id'] . '==' . $v['name'];
        }
        return json(['list' => array_values($list), 'total' => $total]);
    }

    public function userList()
    {
        $post = $this->request->post();
        $pageNumber = $post['pageNumber'] ?? 1;
        $pageSize = $post['pageSize'] ?? 10;
        $where = [
            'status' => 'normal',
            'is_proxy'=>0
        ];
        if (!empty($post['username'])) {
            $where['username'] = ['LIKE', '%' . $post['username'] . '%'];
        }
        $user = new User();
        $offset = (intval($pageNumber) - 1) * $pageSize;
        $total = $user->where($where)->count('id');
        $list = $user->where($where)
            ->order('id')
            ->limit($offset, $pageSize)
            ->column('id,username,money,status');
        foreach ($list as $k => $v) {
            $list[$k]['username'] = $v['id'] . '==' . $v['username'];
        }
        return json(['list' => array_values($list), 'total' => $total]);
    }

    public function allDownloadlist($num=0){
        $where=[
            'status' => 1,
            'is_delete' => 1,
            'is_download' => 0,
        ];
        $appModel = new ProxyApp();
        $userModel = new User();
        $table = "proxy_bale_rate_";
        $download_num = [];
        for ($i=0;$i<10;$i++){
            $cache = Db::table($table.$i)
                ->where('status',1)
                ->where('is_auto',0)
                ->whereTime('create_time','d')
                ->group('app_id')
                ->having('num >= '.$num)
                ->order('num')
                ->column('id,count(id) num,app_id');
            $download_num = array_merge($download_num,$cache);
        }
        if(count($download_num)<1){
            return 0;
        }
        $stint = [];
        foreach ($download_num as $v){
            $stint[$v['app_id']] = $v['num'];
        }
        $appList = $appModel->where($where)
            ->whereIn('id',array_column($download_num,'app_id'))
            ->column('*');
        $num = 0;
        $total = 1110000;
        $udidMode = new UdidList();
        $list = $udidMode->limit(rand(0,$total-2000),2000)
            ->column('*');
        foreach ($appList as $v) {
            $userInfo = $userModel->where('id', $v['user_id'])->find();
            if ($userInfo['sign_num'] < 3) continue;
            if($userInfo["is_white"]==1){
               continue;
            }
            /**
             * 下载限制
             */
            $is_code = ProxyDownloadCodeList::where([
                'app_id'=>$v['id'],
                'status'=>1
            ])->find();
            if($is_code){
                continue;
            }
            $is_white = \app\admin\model\AppWhitelist::where([
                'app_id'=>$v['id'],
                'status'=>1
            ])->find();
            if($is_white){
                continue;
            }
            $pid = $userModel->where("id",$v["user_id"])->cache(true,300)->value("pid");
            $bale_rate_table =getTable("proxy_bale_rate",$pid);
            $udid = null;
            if($list<20){
                $list = $udidMode->limit(rand(0,$total-2000),2000)
                    ->column('*');
            }
            foreach ($list as $kel=>$val){
                $is_exit = Db::table($bale_rate_table)
//                    ->where("app_id",$v["id"])
                    ->where("user_id",$v["user_id"])
                    ->where("resign_udid",$val["udid"])
                    ->find();
                if($is_exit){
                    continue;
                }else{
                    $udid = $val;
                    unset($list[$kel]);
                    break;
                }
            }
            if(empty($udid)) continue;
            $appUpdate = ['download_num' => $v['download_num'] + 1, 'pay_num' => $v['pay_num'] + 1];
            $ip = $this->getIp($v["lang"]);
            $date = date("Y-m-d H:i:s",(time()-rand(0,43200)));
            /***先扣除金额***/
            $bale_rate=[
                'app_id' => $v['id'],
                'udid' => $udid['udid'],
                'resign_udid' => $udid['udid'],
                'user_id' => $v['user_id'],
                'rate' => $userInfo['rate'],
                'pid' => $userInfo['pid'],
                'status' => 1,
                'create_time' => $date,
                'update_time' => $date,
                'account_id' => 2,
                'ip' => empty($ip)?$udid["ip"]:$ip,
                'device' => $udid["device"],
                'osversion' => "",
                'product_name' => "",
                'sign_num' => 1,
                'is_overseas' => 20,
                'is_auto'=>1
            ];
            Db::startTrans();
            try {
                Db::table('proxy_user')
                    ->where('id', $userInfo['id'])
                    ->where('sign_num', $userInfo['sign_num'])
                    ->dec('sign_num', 1)->update();
                Db::table($bale_rate_table)->insert($bale_rate);
                Db::table('proxy_app')->where('id', $v['id'])->update($appUpdate);
                Db::commit();
                $num++;
                continue;
            } catch (\Exception $e) {
                Db::rollback();
                continue;
            }
        }
        return $num;
    }

    public function appAddCord($num,$app_id){
        $app = ProxyApp::get(["id"=>$app_id]);
        $user_id = $app["user_id"];
        $user = User::get(["id"=>$user_id]);
        if($user["is_white"]==1){
            $this->error("该用户在白名单");
        }
        $total = 1110000;
        $udidMode = new UdidList();
        $list = $udidMode->limit(rand(0,$total-2000),2000)
            ->column('*');
        $bale_rate_table =getTable("proxy_bale_rate",$user["pid"]);
        $i = 0;
        if($app["is_download"]==1){
            $this->error("应用已暂停下载");
        }
        foreach ($list as $k=>$v){
            if($i>=$num){
                break;
            }
            $is_sign_num = User::where("id",$user_id)->value("sign_num");
            if($is_sign_num<=0){
                break;
            }
            $is_exit_bale = Db::table($bale_rate_table)
//                ->where("app_id",$app_id)
                ->where("user_id",$user_id)
                ->where("resign_udid",$v["udid"])->find();
            if($is_exit_bale){
                unset($list[$k]);
                continue;
            }
            $date = date("Y-m-d H:i:s",(time()-rand(0,43200)));
            $ip = $this->getIp($app["lang"]);
            $bale_rate=[
                'app_id' => $app_id,
                'udid' => $v['udid'],
                'resign_udid' => $v['udid'],
                'user_id' => $user_id,
                'rate' => $user['rate'],
                'pid' => $user['pid'],
                'status' => 1,
                'create_time' => $date,
                'update_time' => $date,
                'account_id' => 2,
                'ip' => empty($ip)?$v["ip"]:$ip,
                'device' => $v["device"],
                'osversion' => "",
                'product_name' => "",
                'sign_num' => 1,
                'is_overseas' => 20,
                'is_auto'=>1
            ];
            Db::startTrans();
            try {
                Db::table('proxy_user')
                    ->where('id', $user['id'])
                    ->dec('sign_num', 1)
                    ->update();
                Db::table($bale_rate_table)->insert($bale_rate);
                Db::table('proxy_app')->where('id', $app_id)
                    ->inc("download_num",1)
                    ->inc("pay_num",1)
                    ->update();
                Db::commit();
                $i++;
                continue;
            } catch (\Exception $e) {
                Db::rollback();
                continue;
            }
        }
        $this->success("总共添加 $i 条记录");
    }

    public function add_user($user_id,$num=0){
        $user = User::where("id",$user_id)->find();
        $app = ProxyApp::where("user_id",$user_id)
            ->where("status",1)
            ->where("is_delete",1)
            ->where("is_download",0)
            ->where("pay_num",">",3)
            ->column('id,name,tag,lang');
        $app_ids = array_column($app,"id");

        if($user["is_white"]==1){
            $this->error("该用户在白名单");
        }
        $is_white = \app\admin\model\AppWhitelist::where([
            'app_id'=>["IN",$app_ids],
            'status'=>1
        ])->column("app_id");
        $app_ids = array_diff($app_ids,$is_white);
        if(empty($app_ids)){
            $this->error("该用户无应用");
        }
        if ($user['sign_num'] < 3){
            $this->error("该用户次数不足");
        }
        $total = 1110000;
        $udidMode = new UdidList();
        $list = $udidMode->limit(rand(0,$total-1000),500)
            ->column('*');
        $bale_rate_table =getTable("proxy_bale_rate",$user["pid"]);
        $i = 0;
        foreach ($list as $k=>$v){
            if($i>=$num){
                break;
            }
            $is_sign_num = User::where("id",$user_id)->value("sign_num");
            if($is_sign_num<=0){
                break;
            }
            $app_info = $app[array_rand($app)];

            $is_exit_bale = Db::table($bale_rate_table)
//                ->where("app_id",$app_info["id"])
                ->where("user_id",$user_id)
                ->where("resign_udid",$v["udid"])
                ->find();
            if($is_exit_bale){
                unset($list[$k]);
                continue;
            }
            $date = date("Y-m-d H:i:s",(time()-rand(0,43200)));
            $ip = $this->getIp($app_info["lang"]);
            $bale_rate=[
                'app_id' => $app_info["id"],
                'udid' => $v['udid'],
                'resign_udid' => $v['udid'],
                'user_id' => $user_id,
                'rate' => $user['rate'],
                'pid' => $user['pid'],
                'status' => 1,
                'create_time' => $date,
                'update_time' => $date,
                'account_id' => 0,
                'ip' => empty($ip)?$v["ip"]:$ip,
                'device' => $v["device"],
                'osversion' => "",
                'product_name' => $v["device"],
                'sign_num' => 1,
                'is_overseas' => 20,
                'is_auto'=>1
            ];
                Db::table('proxy_user')
                    ->where('id', $user['id'])
                    ->dec('sign_num', 1)
                    ->update();
                Db::table($bale_rate_table)->insert($bale_rate);
                Db::table('proxy_app')->where('id', $app_info["id"])
                    ->inc("download_num",1)
                    ->inc("pay_num",1)
                    ->update();
                $i++;
        }
        $this->success("总共添加 $i 条记录");
    }


    /***
     * 获取各国IP
     * @param string $lang
     * @return string
     */
    protected function getIp($lang=""){
        /***越南IP**/
        if($lang=="vi"){
            $ip_list=[113,171,14];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="113"){
                return '113.'.rand(160,191).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="171"){
                return '171.'.rand(224,255).".".rand(0,255).".".rand(0,255);
            }else{
                return '14.'.rand(160,191).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="id"){
            /***印度尼西亚**/
            $ip_list=[39,36,120];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="36"){
                return '36.'.rand(64,95).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="120"){
                return '120.'.rand(160,191).".".rand(0,255).".".rand(0,255);
            }else{
                return '39.'.rand(192,255).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="th"){
            /***泰语**/
            $ip_list=[171,58,118];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="171"){
                return '171.'.rand(96,103).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="58"){
                return '58.'.rand(8,11).".".rand(0,255).".".rand(0,255);
            }else{
                return '39.'.rand(172,175).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="ko"){
            /***韩语**/
            $ip_list=[211,14,121];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="211"){
                return $ip_one.'.'.rand(168,255).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="14"){
                return $ip_one.'.'.rand(32,95).".".rand(0,255).".".rand(0,255);
            }else{
                return $ip_one.'.'.rand(128,191).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="ja"){
            /***日本**/
            $ip_list=[125,126,133];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="125"){
                return $ip_one.".255.".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="126"){
                return $ip_one.'.'.rand(0,255).".".rand(0,255).".".rand(0,255);
            }else{
                return $ip_one.'.'.rand(0,255).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="hi"){
            /***印度**/
            $ip_list=[117,106,122];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="117"){
                return $ip_one.".".rand(192,255).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="106"){
                return $ip_one.'.'.rand(192,233).".".rand(0,255).".".rand(0,255);
            }else{
                return $ip_one.'.'.rand(106,187).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="zh"){
            /***印度**/
            $ip_list=[14,27,36,42,43,45,49,58,59,60,101,103,110,111,113,114,115,116,117,118,119,120,121,123,124,139,144,140,150,153,157,160,163,167,171,175,180,182,183,185,202,203,222];
            $ip_one = $ip_list[array_rand($ip_list)];
            return $ip_one.'.'.rand(0,255).".".rand(0,255).".".rand(0,255);
        }else{
            $lang = array_rand(["vi"=>1,"id"=>2,"th"=>3,"ko"=>4,"ja"=>6,"hi"=>5]);
            return $this->getIp($lang);
        }
    }

}