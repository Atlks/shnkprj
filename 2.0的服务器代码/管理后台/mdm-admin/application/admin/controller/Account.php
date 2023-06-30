<?php

namespace app\admin\controller;

use app\admin\model\OssConfig;
use app\admin\model\UdidAccount;
use app\common\controller\Backend;
use app\common\library\Oss;
use app\common\model\Config;
use CFPropertyList\CFPropertyList;
use think\Db;
use think\Exception;
use think\exception\PDOException;
use think\exception\ValidateException;
use app\common\library\Redis;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use app\common\model\Config as ConfigModel;
/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Account extends Backend
{
    
    /**
     * Account模型对象
     * @var \app\admin\model\Account
     */
    protected $model = null;

    protected $searchFields="account";
    protected $importHeadType = 'account,password,source';


    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Account;
        $list = Config::where("name","cert_ecs")->value("value");
        $this->assign('ip_list',json_decode($list,true));
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
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where($where)
                ->where("is_own",1)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->where("is_own",1)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            $sign_num = $this->model
                ->where("status",1)
                ->where("is_delete",1)
                ->where("is_own",1)
                ->where("udid_num","<",100)
                ->sum('udid_num');
            $all_count = $this->model
                ->where("status",1)
                ->where("is_delete",1)
                ->where("is_own",1)
                ->where("udid_num","<",100)
                ->count('id');
            $last_num = ($all_count*100-$sign_num)/100;
            $udidAccountModel = new UdidAccount();
            foreach ($list as $k=>$v){
                $list[$k]["use_num"] = $udidAccountModel->where("account_id",$v["id"])
                    ->count();
            }

            $result = array("total" => $total, "rows" => $list,'extend'=>['last_num'=>$last_num]);

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
                $is_exit = $this->model->where('account',trim($params['account']))->value('id');
                if($is_exit){
                    $this->result(null,0,'账号已存在');
                }
                $url = "http://".$params["ip"]."/index/accountLogin";
                $result_check = $this->http_request("http://".$params["ip"]."/index/accountCheckCode",$params);
                $result_check = json_decode($result_check,true);
                if($result_check["result"]["code"]==200){
                    $des = $result_check["result"]["des"];
                    $des_cookie = $result_check["result"]["des_cookie"];
                    $ip = explode(":",$params["ip"]);
                    $params["ip"]=$ip[0];
                    $params["port"]=$ip[1]??80;
                }else{
                    $this->error($result_check["result"]["message"]);
                }
                unset($params['mobile_id']);
                unset($params['mobile_index']);
                $post_data = [
                    'account'=>$params['account'],
                    'password'=>$params['password'],
                    'des'=>$des,
                    'des_cookie'=>$des_cookie,
                ];
                $auth_result = $this->http_request($url,$post_data);
                $auth_result = json_decode($auth_result,true);
                if (isset($auth_result['code'])&&$auth_result['code']==200){
                    if($auth_result['result']['code']==1){
                        $params['oss_path']=$auth_result['result']['data']['oss_path'];
                        $params['udid_num']=$auth_result['result']['data']['udid_num'];
                        $params['cert_id']=$auth_result['result']['data']['cert_id'];
                        $params['team_id']=$auth_result['result']['data']['team_id'];
                        $params['cert_name']=$auth_result['result']['data']['cert_name'];
                    }else{
                        $this->error($auth_result['result']['msg'],"",$result_check);
                    }
                }else{
                    $this->error('网络错误，请稍后重试');
                }
                $params['account'] = trim($params['account']);
                $params['password'] = trim($params['password']);
                $params['create_time']=date('Y-m-d H:i:s');
                $params['status']=0;
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $result = $this->model->allowField(true)->save($params);
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

    /**
     * 编辑
     */
    public function edit($ids = null)
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
//                $Oss = new Oss();
//                /**OSS上传***/
//                $oss_path = 'cert/'.date('Ymd').'/'. $params['account'].'.p12';
//                if(is_file(ROOT_PATH.'/public'.$params['cert_path'])){
//                    $oss_result = $Oss->ossUpload(ROOT_PATH.'/public'.$params['cert_path'],$oss_path);
//                    if(!$oss_result){
//                        $this->result(null,0,'更新应用失败');
//                    }
//                    $params['oss_path']=$oss_path;
//                }
//                if(is_file(ROOT_PATH.'/public'.$params['prov'])){
//                    $provision = $row['provisioning'];
//                    if(empty($provision)){
//                        $q = str_replace('@', 'sclc', trim($params['account']));
//                        $provision = "provisioning/".$q.".mobileprovision";
//                    }
//                    $oss_result = $Oss->ossUpload(ROOT_PATH.'/public'.$params['prov'],$provision);
//                    if(!$oss_result){
//                        $this->result(null,0,'更新应用失败');
//                    }
//                    $params['provisioning']=$provision;
//                }
                $ip_key = $params['ip'];
                unset($params['ip']);
                if($ip_key!=0 &&$ip_key!=='default'){
                    $ip = explode(':',$ip_key);
                    $params['ip']=$ip[0];
                    $params['port']=$ip[1];
                }else{
                    $params['ip']=null;
                }
                $params['account'] = trim($params['account']);
                $params['password'] = trim($params['password']);
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
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
        if($row['ip']){
            $row_key = $row['ip'].":".$row['port'];
            $ip = config('site.cert_ecs');
            if(!array_key_exists($row_key,$ip)){
                $row_key = 'default';
            }
        }else{
            $row_key = 0;
        }
        $this->view->assign("row", $row);
        $this->view->assign("row_key", $row_key);
        return $this->view->fetch();
    }


    /**
     * 启用
     */
    public function start($ids = '')
    {
        if ($ids) {
            $count = false;
            Db::startTrans();
            try {
                $this->model->where('id','in',$ids)->update(['is_delete'=>1,'status'=>1]);
                $count=true;
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
                $this->error(__('启动失败'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));

    }
    /**
     * 禁用
     */
    public function disable($ids = '')
    {
        if ($ids) {
            $count = false;
            Db::startTrans();
            try {
                $this->model->where('id','in',$ids)->update(['status'=>0]);
                $count=true;
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
                $this->error(__('禁用失败'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

//    /**
//     * 证书测试
//     * @param string $ids
//     * @return string
//     * @throws Exception
//     * @throws \think\exception\DbException
//     */
//    public function testAccount($ids=''){
//        if($this->request->isPost()){
//            $params = $this->request->post("row/a");
//            $udid = $params['udid'];
//            $account =  $account = $this->model->get($params['id']);
//            $tag = strstr($account['account'], '@', true);
//            $this->http_async_request(config('ios_test_account'),['udid'=>$udid,'account_id'=>$account['id']]);
//            $iosPackage = new IosPackage();
////            $callback = 'https://'.config('url_callback').'/callback/' . $tag . '/' . $udid;
//            $callback = 'https://oss.5jj34.cn/testSign/'.$tag.'.ipa';
//            $bundle = 'com.' . $tag . '.www';
//            $cache_plist = $iosPackage->addTemporaryPList('TEST', $udid, $bundle, '', $callback);
//            $down_url = 'itms-services://?action=download-manifest&url=https://' .config('url_domain'). $cache_plist;
//            $this->result(['url'=>$down_url],1,'success');
//        }
//        $account = $this->model->get($ids);
//        $this->view->assign('row',$account);
//        return $this->view->fetch();
//    }

//    public function test($ids=''){
//        if($this->request->isPost()){
//            $params = $this->request->post("row/a");
//            $apple = new AppleAuth();
//            $result = $apple->securityCode($params['account'],$params['code'],$params['mobile'],$params['mode'],
//                $params['cookie'],$params['session_id'],$params['scnt']);
//            if($result['code']==200){
//                $url = "http://127.0.0.1:9501/index/accountLogin";
//                $auth_result = $this->http_request($url,['account'=>$params['account'],'password'=>$params['password']]);
//                var_dump($auth_result);
//                /**
//                 * TODO::后续证书获取操作
//                 */
//                $this->error('555');
//            }else{
//                $this->error($result['message']);
//            }
//        }
//        $account = $this->model->get($ids);
//        $this->view->assign('row',$account);
//        return $this->view->fetch();
//    }

    /**
     * 签名认证
     */
    public function sign(){
        $post = $this->request->post("row/a");
        $is_exit = $this->model->where('account',trim($post['account']))->value('id');
        if($is_exit){
            $this->result(null,0,'账号已存在');
        }
        if($post["ip"]!=0){
            $url="http://".$post["ip"]."/index/accountSign";
            $result = $this->http_request($url,$post);
            $result = json_decode($result,true);
        }else{
            $this->result(null,0,"请选择验证服务器");
        }
        /**单账号**/
        if($result["result"]['code']==201){
            $this->result($result["result"]['body'],1);

        }elseif ($result["result"]['code']==423){  /***验证码发送过多**/
            $this->result($result["result"]['body'],2,$result["result"]['body']['message']);
        }elseif($result["result"]['code']==202||$result["result"]['code']==200){ /**多账号**/
            $this->result($result["result"]['body'],3);
        }else{
            $this->result(null,0,$result["result"]['body']);
        }
    }

    /**
     * 发送验证码
     */
    public function sendCode(){
        $post = $this->request->post("row/a");
        $url = "http://".$post["ip"]."/index/accountSendCode";
        $result =  $this->http_request($url,$post);
         $result = json_decode($result,true);
        $res = $result["result"];
        if($res['code']==200){
            $this->result($res["body"],1);
        }elseif ($res['code']==423){
            $this->result($res["body"],2,$res['body']['message']);
        }else{
            $this->result(null,0,$res['body']??"发送失败");
        }
    }

    /**
     * 导入
     */
    public function import()
    {
        $file = $this->request->request('file');
        if (!$file) {
            $this->error(__('Parameter %s can not be empty', 'file'));
        }
        $filePath = ROOT_PATH . DS . 'public' . DS . $file;
        if (!is_file($filePath)) {
            $this->error(__('No results were found'));
        }
        //实例化reader
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
            $this->error(__('Unknown data format'));
        }
        if ($ext === 'csv') {
            $file = fopen($filePath, 'r');
            $filePath = tempnam(sys_get_temp_dir(), 'import_csv');
            $fp = fopen($filePath, "w");
            $n = 0;
            while ($line = fgets($file)) {
                $line = rtrim($line, "\n\r\0");
                $encoding = mb_detect_encoding($line, ['utf-8', 'gbk', 'latin1', 'big5']);
                if ($encoding != 'utf-8') {
                    $line = mb_convert_encoding($line, 'utf-8', $encoding);
                }
                if ($n == 0 || preg_match('/^".*"$/', $line)) {
                    fwrite($fp, $line . "\n");
                } else {
                    fwrite($fp, '"' . str_replace(['"', ','], ['""', '","'], $line) . "\"\n");
                }
                $n++;
            }
            fclose($file) || fclose($fp);

            $reader = new Csv();
        } elseif ($ext === 'xls') {
            $reader = new Xls();
        } else {
            $reader = new Xlsx();
        }

        //导入文件首行类型,默认是注释,如果需要使用字段名称请使用name
        $importHeadType = isset($this->importHeadType) ? $this->importHeadType : 'comment';

        $table = $this->model->getQuery()->getTable();
        $database = \think\Config::get('database.database');
        $fieldArr = [];
        $list = db()->query("SELECT COLUMN_NAME,COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?", [$table, $database]);
        foreach ($list as $k => $v) {
            if ($importHeadType == 'comment') {
                $fieldArr[$v['COLUMN_COMMENT']] = $v['COLUMN_NAME'];
            } else {
                $fieldArr[$v['COLUMN_NAME']] = $v['COLUMN_NAME'];
            }
        }

        //加载文件
        $insert = [];
        try {
            if (!$PHPExcel = $reader->load($filePath)) {
                $this->error(__('Unknown data format'));
            }
            $currentSheet = $PHPExcel->getSheet(0);  //读取文件中的第一个工作表
            $allColumn = $currentSheet->getHighestDataColumn(); //取得最大的列号
            $allRow = $currentSheet->getHighestRow(); //取得一共有多少行
            $maxColumnNumber = Coordinate::columnIndexFromString($allColumn);
            $fields = [];
            for ($currentRow = 1; $currentRow <= 1; $currentRow++) {
                for ($currentColumn = 1; $currentColumn <= $maxColumnNumber; $currentColumn++) {
                    $val = $currentSheet->getCellByColumnAndRow($currentColumn, $currentRow)->getValue();
                    $fields[] = $val;
                }
            }

            for ($currentRow = 2; $currentRow <= $allRow; $currentRow++) {
                $values = [];
                for ($currentColumn = 1; $currentColumn <= $maxColumnNumber; $currentColumn++) {
                    $val = $currentSheet->getCellByColumnAndRow($currentColumn, $currentRow)->getValue();
                    $values[] = is_null($val) ? '' : $val;
                }
                $row = [];
                $temp = array_combine($fields, $values);
                foreach ($temp as $k => $v) {
                    if (isset($fieldArr[$k]) && $k !== '') {
                        $row[$fieldArr[$k]] = $v;
                    }
                }
                if ($row) {
                    $insert[] = $row;
                }
            }
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }
        if (!$insert) {
            $this->error(__('No rows were updated'));
        }
        $count = 0;
        try {
            $options = [
                'host' => 'redis.ios.com',
                'port' => '6379',
                'password' => '',
                'select' => 6,
                'timeout' => 0,
                'expire' => 0,
                'persistent' => false,
                'prefix' => '',
            ];
            $redis = new Redis($options);
            $logModel = new AccountAutoAddLog();
            $insertAll = [];
            $group = date('YmdHis');//组别
            $value = ConfigModel::where(['name' => 'cert_ecs'])->value('value');
            $ip_arr = json_decode($value,true);
            unset($ip_arr['default']);

            foreach ($insert as $key=>&$val) {
                $is_exit = $logModel->where(['account'=>$val['account'],'status'=>['neq',2]])->find();
                if($is_exit || empty($val['account']) || empty($val['password'])){
                    continue;
                }
                $account = $this->model->where(['account'=>$val['account']])->find();
                if($account){
                    $insertAll[$key]['status'] = 2;//失败
                    $insertAll[$key]['error_type'] = '账号';
                    $insertAll[$key]['msg'] = '账号已存在';
                    $insertAll[$key]['update_time'] = date("Y-m-d H:i:s");
                }else{
                    $insertAll[$key]['status'] = 0;//导入中
                    $insertAll[$key]['error_type'] = '';
                    $insertAll[$key]['msg'] = '';
                    $ip = array_rand($ip_arr);
                    if($ip != 0){
                        $insert_ip = explode(":",$ip);
                        $val['ip'] = $insert_ip[0];
                        $val['port'] = $insert_ip[1];
                    }else{
                        $val['ip'] = '';
                        $val['port'] = '80';
                    }
                    $insertAll[$key]['ip'] = $ip;
                    $val['account'] = trim($val['account']);
                    $val['password'] = trim($val['password']);
                    $val['source'] = trim($val['source']);
                    $redis->handle()->lPush('account_list',json_encode($val));
                }
                $insertAll[$key]['account'] = $val['account'];
                $insertAll[$key]['password'] = $val['password'];
                $insertAll[$key]['source'] = $val['source'];;
                $insertAll[$key]['group'] = $group;
                $insertAll[$key]['create_time'] = date("Y-m-d H:i:s");
                $count++;
            }
            $logModel->saveAll($insertAll);
            $this->http_async_request("http://task.ios.com/account/autoAdd");
        } catch (PDOException $exception) {
            $msg = $exception->getMessage();
            if (preg_match("/.+Integrity constraint violation: 1062 Duplicate entry '(.+)' for key '(.+)'/is", $msg, $matches)) {
                $msg = "导入失败，包含【{$matches[1]}】的记录已存在";
            };
            $this->error($msg);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        if(is_file($filePath)){
            @unlink($filePath);
        }
        $this->success('导入成功，共导入'.$count.'条');
    }

    /**
     * 账号检测
     */
    public function check(){
        $url = "http://34.92.75.231:85/index/checkAccountOcsp";
        $result = $this->http_request($url);
        $data = json_decode($result,200);
        if($data["code"]==200){
            $this->success('账号检测开始');
        }else{
            $this->error('账号检测失败，请重试');
        }
    }

    /**
     * 账号检测
     */
    public function ocsp_check(){
        $url = "http://34.92.75.231:85/index/checkAccountOcsp";
        $result = $this->http_request($url);
        $data = json_decode($result,200);
        if($data["code"]==200){
            $this->success('账号OCSP检测开始');
        }else{
            $this->error('账号检测失败，请重试');
        }
    }

    /**
     * udid对应关系更新
     * @param string $ids
     * @throws \CFPropertyList\IOException
     * @throws \CFPropertyList\PListException
     * @throws \DOMException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function update_udid($ids=''){
        $account = $this->model->where("id",$ids)
            ->where("status",1)
            ->where("is_delete",1)
            ->find();
        if(empty($account)){
            $this->error("账号不存在");
        }
        $path = ROOT_PATH."runtime/mobileprovision/";
        if(!is_dir($path)){
            mkdir($path,0777,true);
        }
        $oss_config=OssConfig::where("name","oss")
            ->where("status",1)
            ->find();
        $oss = new Oss($oss_config);
        $provision = $ids.".mobileprovision";
        if($oss->ossDownload($account["provisioning"],$path.$provision)){
            if(is_file($path.$provision)){
                exec("cd $path && openssl cms -verify -in $provision -inform DER -noverify ",$log,$status);
                if($status==0){
                    $string = implode("\r\n",$log);
                    $plist = new CFPropertyList();
                    $plist->parse($string,CFPropertyList::FORMAT_AUTO);
                    $data = $plist->toArray();
                    if(isset($data["ProvisionedDevices"])&&count($data["ProvisionedDevices"])>1){
                        $udids = $data["ProvisionedDevices"];
                        $saveAll = [];
                        foreach ($udids as $v){
                            $saveAll[]=[
                                "account_id"=>$ids,
                                "udid"=>$v,
                            ];
                        }
                        $this->model->where("id",$ids)->update(["udid_num"=>count($udids)]);
                        $udidAccountModel = new UdidAccount();
                        $udidAccountModel->where("account_id",$ids)->delete();
                        $udidAccountModel->saveAll($saveAll,false);
                        @unlink($path.$provision);
                        $this->success("已重置udid关系对应表,udid数量：".count($udids));
                    }else{
                        @unlink($path.$provision);
                        $this->error("UDID数量小于10，重置失败");
                    }
                }
            }
        }
        $this->error("描述文件下载错误，请稍后重试");
    }

}
