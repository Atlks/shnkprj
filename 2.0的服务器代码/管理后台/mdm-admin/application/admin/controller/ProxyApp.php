<?php

namespace app\admin\controller;

use app\admin\model\proxy\User;
use app\admin\model\ProxyAppUpdateLog;
use app\admin\model\ProxyUser;
use app\admin\model\ProxyUserDomain;
use app\admin\model\TestApp;
use app\common\controller\Backend;
use app\admin\model\OssConfig;
use app\common\library\GoogleOss;
use app\common\library\Ip2Region;
use app\common\library\Oss;
use app\common\library\Redis;
use app\common\model\Config;
use fast\Random;
use think\Db;
use think\Exception;
use think\exception\PDOException;
use app\admin\model\AppWhitelist;
use app\admin\model\AutoAppRefush;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class ProxyApp extends Backend
{

    /**
     * ProxyApp模型对象
     * @var \app\admin\model\ProxyApp
     */
    protected $model = null;

    protected $searchFields = "name";

    protected $multiFields = "status,is_tip,is_download,is_mac,is_st,is_append,is_admin,is_v1,is_en_callback,is_apiSign";

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\ProxyApp;
        $lang_list = [
            "zh" => "中文", "tw" => "繁体", "en" => "英语 ", "vi" => "越南", "id" => "印度尼西亚", "th" => "泰语",
            "ko" => "韩语", "ja" => "日语", "hi" => "印地语", "es" => "西班牙语", "pt" => "葡萄牙语", 'tr' => "土耳其", "ru" => "俄语", "ms" => "马来语", "fr" => "法语", "de" => "德语", 'lo' => '老挝语'
        ];
        $this->assign('lang_list', $lang_list);
        $account_list = (new \app\admin\model\Enterprise())->column("id,name");
        foreach ($account_list as $k => &$v) {
            $v = $v . '(' . $k . ')';
        }
        $this->assign("account_list", $account_list);
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
        $ip = $this->request->ip();
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        //查询权限
        $groups = $this->auth->getGroupIds($this->auth->id);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $filter = $this->request->get("filter", '');
            $filter = (array)json_decode($filter, true);
            if (array_key_exists("auto_app_refush.status", $filter)) {
                $app_id =  AutoAppRefush::where("status", 1)->column("app_id");
                if ($filter["auto_app_refush.status"] == 1) {
                    $where_1 = [
                        "proxy_app.id" => ["IN", $app_id],
                        "proxy_app.is_admin" => 1
                    ];
                } else {
                    $where_1 = [
                        "proxy_app.id" => ["NOT IN", $app_id],
                        "proxy_app.is_admin" => 1
                    ];
                }
            } else {
                $where_1 = [
                    "proxy_app.is_admin" => 1
                ];
            }
            if (!in_array(1, $groups)) {
                $where_1["proxy_app.user_id"] = ["NOT IN", [6, 7]];
            } else {
                $where_1["proxy_app.user_id"] = ["<>", 6];
            }
            $total = $this->model
                ->with(['proxyuser'])
                ->where($where)
                ->where($where_1)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['proxyuser'])
                ->where($where)
                ->where($where_1)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            $ip2 = new Ip2Region();
            $ip_address = $ip2->binarySearch($this->request->ip());
            $address = explode('|', $ip_address['region']);
            if ($address[0] == "中国" && !in_array($address[2], ["澳门", '香港', "台湾省"])) {
                $public_url =  Config::where('name', 'proxy_zh_oss_public_url')->value('value');
            } else {
                $public_url =  Config::where('name', 'proxy_en_oss_public_url')->value('value');
            }
            foreach ($list as $row) {
                $domain = ProxyUserDomain::where("user_id", $row->proxyuser->pid)
                    ->cache(true, 300)
                    ->find();
                $row->getRelation('proxyuser')->visible(['username', 'pid']);
                $url = $domain["download_url"];
                $port_data = \app\admin\model\DownloadUrl::where("name", $url)
                    ->where("status", 1)
                    ->cache(true, 180)
                    ->find();
                if (empty($port_data)) {
                    $port_data = \app\admin\model\DownloadUrl::where("status", 1)
                        ->where("is_default")
                        ->cache(true, 180)
                        ->find();
                }
                /**带端口**/
                if (!empty($port_data["admin_port"])) {
                    $ports = explode(",", $port_data["admin_port"]);
                    $port = $ports[array_rand($ports)];
                    $url = "$url:$port";
                }
                if ($domain["ext"] == "app") {
                    $row['url'] = 'https://' . $url . '/' . $row['tag'] . '.app';
                } else {
                    //$row['url']='https://'.$url.'/'.$row['short_url'].'.html';
                    $row['url'] = 'https://' . $url . '/' . $row['short_url'];
                }
                $row['filesize'] = format_bytes($row['filesize']);
                $row["icon"] = $public_url . "/" . substr($row["icon"], strpos($row["icon"], 'upload/'));
                if (!empty($row["old_icon"])) {
                    $row["old_icon"] =  $public_url . "/" . substr($row["old_icon"], strpos($row["old_icon"], 'upload/'));
                }
                $auto_app_refush = AutoAppRefush::where("app_id", $row["id"])->find();
                if ($auto_app_refush) {
                    $row["auto_app_refush"] = $auto_app_refush;
                } else {
                    $row["auto_app_refush"] = ["scale" => null, "status" => 0];
                }
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        if (in_array(1, $groups) || in_array(23, $groups)) {
            $visable = true;
            $scale = true;
            $is_operate = "=";
        } else {
            $visable = false;
            $scale = false;
            $is_operate = false;
        }
        $show_st = $this->auth->check("proxy_app/upload_st_moblieconfig");
        $this->assignconfig("is_showColumn", $visable);
        $this->assignconfig("is_scale", $scale);
        $this->assignconfig("is_operate", $is_operate);
        $this->assignconfig("is_showSt", $show_st);
        return $this->view->fetch();
    }
    /***
     * 开启接口签名 默认是星空签名
     * @param string $ids
     */
    public function open_xk_sign($ids = "")
    {
        $app = $this->model->where("id", $ids)
            ->where("is_apiSign", 0)
            ->find();
        if ($app['is_add'] === 1) {
            $this->error("请先通过审核");
        } else {
            $update = [
                'id' => $app['id'],
                'is_apiSign' => 1
            ];
            \app\admin\model\ProxyApp::update($update);
            $this->xingkong_sign($ids);
        }
        $this->assign("app", $app);
        return $this->view->fetch();
    }

    /**
     * 关闭开启接口签名 默认是星空签名
     * @param string $ids
     */
    public function close_xk_sign($ids = "")
    {
        $app = $this->model->where("id", $ids)
            ->where("is_apiSign", 1)
            ->find();
        $update = [
            'id' => $app['id'],
            'is_apiSign' => 0
        ];
        \app\admin\model\ProxyApp::update($update);
        $this->success("成功关闭接口签");
        $this->assign("app", $app);
        return $this->view->fetch();
    }
     /**
     * 更新接口签的包 默认是星空签名
     * @param string $ids
     */
    public function updata_xk_sign($ids = "")
    {
        $app = $this->model->where("id", $ids)
            ->find();
        if ($app["is_apiSign"] === 1) {
            //星空
            $this->xingkong_sign($ids);
        }else{
            $this->error("请先开启接口签");
        }
        $this->assign("app", $app);
        return $this->view->fetch();
    }
    /***
     * 星空 签名
     * @param string $ids
     * @param mixed
     */
    public function xingkong_sign($ids = "",$post_data = null)
    {
        $app = $this->model->where("id", $ids)->find();
        
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $app_id = $app['id'];
            if($app['type'] == 1){
                $oss_path = $app['oss_path'];
            }else{
                $this->error("v3签名为空");
                /*$v3 = V3signRecord::where("app_id", $ids)
                ->order('id desc')->limit(1)->find();
                if(!empty($v3)){
                    $oss_path = $v3['oss_path'];
                }else{
                    $this->error("v3签名为空");
                }*/
            }
            $xk_appid = $app['xk_appid'];
            if(is_null($post_data))
            {
                $post_data = [
                    'app_id' => $app_id,
                    'app_path' => $oss_path,
                    'tag' => $app['tag'],
                    'xk_appid' => $app['xk_appid'],
                    'app_type' => $app['type'],
                    'is_init' => 0,
                ];
            }
            else
            {
                $post_data['is_init'] = 1;
            }
           
            //星空 check redis 
            // 如果redis里正在进行
            // 返回错误提示
            $update_data = [
                'id' => $app["id"],
                "is_update" => 0
            ];
            $redis_check = new Redis(["select" => 8]);
            $tag = $redis_check->handle()->get("sign_app_loading:" . $app["id"]);
            if ($tag === $app["tag"]) {
                $this->error("签名正在进行中,请稍后再试");
            }
            if (empty($xk_appid)) {
                $result = $this->http_request("http://34.150.24.210:85/index/sign_Xk_app", $post_data);
            } else {
                $result = $this->http_request("http://34.150.24.210:85/index/sign_updata_Xk_app", $post_data);
            }
            $result_async = json_decode($result, true);
            if (empty($result_async["code"]) || $result_async["code"] != 200) {
                $this->error("更新失败，请刷新重试");
            }
            if (\app\admin\model\ProxyApp::update($update_data)) {
                $is_exit = TestApp::where("app_id", $app["id"])->find();
                if ($is_exit) {
                    $this->model->where("id", $is_exit["test_app_id"])->delete();
                    TestApp::where("id", $is_exit["id"])->delete();
                }
                $redis = new Redis(["select" => 4]);
                $redis->handle()->del("app_tag:" . $app["tag"]);
                $redis->handle()->del("app_short_url:" . $app["short_url"]);
                $redis->handle()->close();
                /**签名开始***/
                $redis_check = new Redis(["select" => 8]);
                $redis_check->handle()->set("sign_app_loading:" . $app["id"], $app["tag"], 120);
                $redis_check->handle()->close();
                $this->success("签名任务已提交");
            } else {
                $this->error("签名失败，请稍后重试");
            }
        }
    }
    /***
     * 批量星空 签名
     * @param string $ids
     * @return mixed
     */
    public function alib_sign_xingkong()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if (empty($params['user_id'])) {
                $this->error("请选择一个用户");
            }
            $post_data = [
                'sign' => 'sdfhjiudfyiunmfhuxingk',
                'user_id' => $params['user_id'],
                'start_time' => 0,
                'end_time' => 0,
                'download_link' => $params['download_link'],
                'download_num' => intval($params['download_num']) ?? 0,
                'status' => $params['status'] ?? 1,
                'is_download' => $params['is_download'] ?? 1,
                'is_delete' => $params['is_delete'] ?? 1
            ];
            if (!empty($params['download_time'])) {
                $downloadTime = explode(' - ', $params['download_time']);
                $post_data['start_time'] = $downloadTime[0];
                $post_data['end_time'] = $downloadTime[1];
            }
            $async = $this->http_request("http://35.241.123.37:85/api/xingkong_sign_batch", $post_data);
            $result_async = json_decode($async, true);
            if (empty($result_async["code"]) || $result_async["code"] != 200) {
                $this->error("批量星空签名任务提交失败，请稍后重试", $result_async);
            } else {
                $this->success("批量星空签名任务已提交");
            }
            
            $this->success("批量星空签名任务已提交");
        }
        
        return $this->view->fetch();
    }
    /**
     * 批量更新
     */
    public function multi($ids = "")
    {
        $ids = $ids ? $ids : $this->request->param("ids");
        if ($ids) {
            $action = $this->request->param("action");
            if ($action === "sign") {
                return $this->multi_sign($ids);
            }
            if ($this->request->has('params')) {
                parse_str($this->request->post("params"), $values);
                if (isset($values['status']) && $values['status'] != 1) {
                    $values['status'] = -1;
                }
                $values = array_intersect_key($values, array_flip(is_array($this->multiFields) ? $this->multiFields : explode(',', $this->multiFields)));
                if ($values || $this->auth->isSuperAdmin()) {
                    $adminIds = $this->getDataLimitAdminIds();
                    if (is_array($adminIds)) {
                        $this->model->where($this->dataLimitField, 'in', $adminIds);
                    }
                    /**前端上架**/
                    if (isset($values["status"]) && $values["status"] == 1) {
                        $values["is_stop"] = 0;
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
                        $this->clear_app_cache($ids);
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

    public function multi_sign($ids = "")
    {
        $list = $this->model->whereIn("id", $ids)->where("status", 1)
            ->where("is_delete", 1)
            ->column("id,name,tag,oss_path,account_id,package_name");
        if (empty($list)) {
            $this->error("未更新任何数据");
        }
        $i = 0;
        foreach ($list as $v) {
            $account = \app\admin\model\Enterprise::where("id", $v["account_id"])
                ->where("status", 1)
                ->find();
            if (empty($account)) {
                $account = \app\admin\model\Enterprise::where("status", 1)
                    ->find();
                if (empty($account)) {
                    $this->error("暂无可用账号");
                }
            }
            $post = [
                'path' => $v['oss_path'],
                'oss_path' =>  'app/' . date('Ymd') . '/' . $v["tag"] . '.ipa',
                'cert_path' => $account["oss_path"],
                'provisioning_path' => $account["oss_provisioning"],
                'password' => $account["password"],
                'account_id' => $account["id"],
                'app_id' => $v['id'],
                'package_name' => $v['package_name'],

            ];
            $sign_url = Config::where("name", "g_ipa_parsing")->value("value");
            $async = $this->http_request("http://$sign_url/index/sign", $post);
            $result_async = json_decode($async, true);
            if (empty($result_async["code"]) || $result_async["code"] != 200) {
                continue;
            } else {
                $i++;
            }
        }
        $this->success("总共更新  $i 行数据");
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
                $this->model->where('id', 'in', $ids)->update(['status' => 1]);
                $count = true;
                Db::commit();
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->clear_app_cache($ids);
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
                $this->model->where('id', 'in', $ids)->update(['status' => -1]);
                $count = true;
                Db::commit();
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->clear_app_cache($ids);
                $this->success();
            } else {
                $this->error(__('禁用失败'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }


    public function auto_refush($ids = "")
    {
        if ($this->request->isPost()) {
            $params = $this->request->param("row/a");
            $data = [
                "app_id" => $ids,
                "status" => $params["status"],
                "scale" => $params["scale"],
            ];
            if (empty($params["auto_id"])) {
                $is_exit =  AutoAppRefush::where("app_id", $ids)
                    ->find();
                if (empty($is_exit)) {
                    $data["create_time"] = date("Y-m-d H:i:s");
                    AutoAppRefush::create($data);
                } else {
                    $data["id"] = $is_exit["id"];
                    AutoAppRefush::update($data);
                }
            } else {
                $data["id"] = $params["auto_id"];
                AutoAppRefush::update($data);
            }
            $this->success("概率已更新");
        }
        $app = $this->model->where("id", $ids)->find();
        $auto = AutoAppRefush::where("app_id", $ids)
            ->find();
        $this->assign("app", $app);
        $this->assign("auto", $auto);
        return $this->view->fetch();
    }


    /**
     * 白名单
     * @param string $ids
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function app_whitelist($ids = "")
    {
        $is_exit = AppWhitelist::where("app_id", $ids)
            ->find();
        if ($is_exit) {
            if ($is_exit["status"] == 1) {
                $this->success("APP已添加白名单");
            } else {
                AppWhitelist::update(["id" => $is_exit["id"], "status" => 1]);
                $this->success("添加白名单成功");
            }
        } else {
            $data = [
                "app_id" => $ids,
                "status" => 1,
                "create_time" => date("Y-m-d H:i:s"),
            ];
            if (AppWhitelist::create($data)) {
                $this->success("添加白名单成功");
            } else {
                $this->error("添加失败，请稍后再试");
            }
        }
    }

    /***
     * linux 签名
     * @param string $ids
     * @return mixed
     */
    public function alib_sign($ids = "")
    {
        $app = $this->model->where("id", $ids)->find();

        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if (empty($params["account_id"])) {
                $this->error("请选择签名证书");
            }
            $account = \app\admin\model\Enterprise::where("id", $params["account_id"])
                ->find();
            if (empty($account)) {
                $this->error("证书不存在，请重新选择");
            }
            $oss_config = OssConfig::where("status", 1)
                ->where("name", "oss")
                ->cache(true, 300)
                ->find();
            $is_google = (new GoogleOss())->exists($app['oss_path']);
            if (!$is_google) {
                $this->error("国内OSS暂无资源，请先点击 同步阿里云");
            }
            $g_oss_config = OssConfig::where("status", 1)
                ->where("name", "g_oss")
                ->cache(true, 300)
                ->find();
            $g_oss = new Oss($g_oss_config);
            $is_g_oss = $g_oss->isExitFile($app['oss_path']);
            if (!$is_g_oss) {
                $this->error("国内OSS暂无资源，请先点击 同步阿里云");
            }
            if (!$is_google && $is_g_oss) {
                $this->error("国内OSS暂无资源，请先点击 包同步查询");
            }
            $oss = new Oss($oss_config);
            $is_oss = $oss->isExitFile($app['oss_path']);
            /**国内无包**/
            if (!$is_oss) {
                $this->error("国内OSS暂无资源，请先点击 包同步查询");
            }
            if (!$is_google && !$is_g_oss) {
                $this->error("OSS暂无资源，请重新上传IPA");
            }
            /**签名***/
            $redis_check = new Redis(["select" => 8]);
            $is_sign = $redis_check->handle()->get("sign_app_loading:" . $app["id"]);
            $redis_check->handle()->close();
            if (empty($app["update_time"]) || !empty($is_sign)) {
                $this->error("签名进行中，无法更换证书");
            }
            $user = User::where(['id' => $app["user_id"], 'status' => 'normal'])->find();
            /**OSS分流***/
            $proxy = ProxyUserDomain::where(['user_id' => $user['pid']])->find();
            if ($proxy["oss_id"]) {
                $async_oss_config = OssConfig::where("id", $proxy["oss_id"])
                    ->find();
            } else {
                $async_oss_config = null;
            }
            $oss_path = 'app/' . date('Ymd') . '/' . $app["tag"] . '.ipa';
            $ansyc_data = [
                'path' => $app['oss_path'],
                'oss_path' => $oss_path,
                'cert_path' => $account["oss_path"],
                'provisioning_path' => $account["oss_provisioning"],
                'password' => $account["password"],
                'account_id' => $account["id"],
                'app_id' => $app['id'],
                'package_name' => $app['package_name'],
                'is_resign' => $app["is_resign"],
                "tag" => $app["tag"],
                "oss_id" => $proxy["oss_id"],
                "async_oss_config" => $async_oss_config
            ];
            /**国内负载签名**/
            $async = $this->http_request("http://39.108.128.140:85/index/alib_int_app", $ansyc_data);
            $result_async = json_decode($async, true);
            if (empty($result_async["code"]) || $result_async["code"] != 200) {
                $this->error("签名失败，请稍后重试", $result_async);
            } else {
                /**签名开始***/
                $redis_check = new Redis(["select" => 8]);
                $redis_check->handle()->set("sign_app_loading:" . $app["id"], $app["tag"], 120);
                $redis_check->handle()->close();
                $this->success("签名任务已提交");
            }
        }
        $this->assign("app", $app);
        return $this->view->fetch();
    }

    /***
     * 批量linux 签名
     * @param string $ids
     * @return mixed
     */
    public function alib_sign_batch()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if (empty($params['account_id_old'])) {
                $this->error("请选择现在使用的签名证书");
            }
            if (empty($params['account_id_new'])) {
                $this->error("请选择替换成的新签名证书");
            }
            $account = \app\admin\model\Enterprise::where('id', $params['account_id_new'])
                ->find();
            if (empty($account)) {
                $this->error("新证书不存在，请重新选择");
            }

            $post_data = [
                'sign' => 'sdfhjiudfyiunmfhu',
                'account_id_old' => $params['account_id_old'],
                'account_id_new' => $params['account_id_new'],
                'user_id' => $params['user_id'],
                'start_time' => 0,
                'end_time' => 0,
                'download_link' => $params['download_link'],
                'download_num' => intval($params['download_num']) ?? 0,
                'status' => $params['status'] ?? 1,
                'is_download' => $params['is_download'] ?? 1,
                'is_delete' => $params['is_delete'] ?? 1
            ];
            if (!empty($params['download_time'])) {
                $downloadTime = explode(' - ', $params['download_time']);
                $post_data['start_time'] = $downloadTime[0];
                $post_data['end_time'] = $downloadTime[1];
            }
            $async = $this->http_request("http://35.241.123.37:85/api/alib_sign_batch", $post_data);
            $result_async = json_decode($async, true);
            if (empty($result_async["code"]) || $result_async["code"] != 200) {
                $this->error("批量linux签名任务提交失败，请稍后重试", $result_async);
            } else {
                $this->success("批量linux签名任务已提交");
            }
        }
        return $this->view->fetch();
    }

    /**
     * 批量签名
     * @param string $ids
     */
    //    protected function sign($ids = ""){
    //        $app = $this->model->where("id",$ids)->find();
    //        $account = \app\admin\model\Enterprise::where("id",$app["account_id"])
    //            ->where("status",1)
    //            ->find();
    //        if(empty($account)){
    //            $account = \app\admin\model\Enterprise::where("status",1)
    //                ->find();
    //            if(empty($account)){
    //                $this->error("暂无可用账号");
    //            }
    //        }
    //        $post = [
    //            'path' => $app['oss_path'],
    //            'package_name' => $app['package_name'],
    //            'oss_path' =>  'app/' . date('Ymd') . '/' . $app["tag"] . '.ipa',
    //            'cert_path' => $account["oss_path"],
    //            'provisioning_path' => $account["oss_provisioning"],
    //            'password' => $account["password"],
    //            'account_id' => $account["id"],
    //            'app_id' => $app['id'],
    //            "is_overseas"=>20,
    //            "tag"=>$app["tag"],
    //        ];
    //        $sign_url = Config::where("name", "g_ipa_parsing")->value("value");
    ////        $async = $this->http_request("http://$sign_url/index/sign", $post);
    //        $async = $this->http_request("http://8.218.75.38:85/index/sign", $post);
    //        $result_async = json_decode($async,true);
    //        if (empty($result_async["code"]) || $result_async["code"] != 200) {
    //            $this->error("签名失败，请稍后重试", $result_async);
    //        }else{
    //            $this->success("签名任务已提交");
    //        }
    //    }

    //    protected function app_upload($ids=""){
    //        $app = $this->model->where("id",$ids)->find();
    //        if($this->request->isPost()){
    //            $post = $this->request->post("row/a");
    //            if(empty($post["path"])){
    //                $this->error("请先上传包在更新");
    //            }
    //            $post = [
    //                'path' => $post['path'],
    //                'oss_path' =>  'app/' . date('Ymd') . '/' . $app["tag"] . '.ipa',
    //                'app_id' => $app['id']
    //            ];
    //            $sign_url = Config::where("name", "g_ipa_parsing")->value("value");
    //            $async = $this->http_request("http://$sign_url/index/app_update", $post);
    //            $result_async = json_decode($async,true);
    //            if (empty($result_async["code"]) || $result_async["result"]["code"] != 1) {
    //                $this->error("更新包失败，请稍后重试", "",$result_async);
    //            }else{
    //                $this->success("更新任务已提交");
    //            }
    //        }
    //        $this->assign("app",$app);
    //        return $this->view->fetch();
    //    }



    public function apk_upload($ids = "")
    {
        $app = $this->model->where("id", $ids)->find();
        if ($this->request->isPost()) {
            $post = $this->request->post("row/a");
            if (empty($post["apk"])) {
                $this->error("请先上传包在更新");
            }
            $update = [
                "id" => $app["id"],
                "apk_url" => $post["apk"]
            ];
            if (\app\admin\model\ProxyApp::update($update)) {
                $this->clear_app_cache($ids);
                $this->success("安卓包更新成功");
            } else {
                $this->success("安卓包更新失败");
            }
        }
        $this->assign("app", $app);
        return $this->view->fetch();
    }

    //    protected function sign_url(){
    //        $oss_config=OssConfig::where("name","g_oss")
    //            ->where("status",1)
    //            ->cache(true,10*60)
    //            ->find();
    //        $oss = new Oss($oss_config);
    //        $result = $oss->policy();
    //        $this->success("success",'',$result);
    //    }

    public function push($ids = "")
    {
        $app = $this->model->where("id", $ids)->find();
        if ($app) {
            $post_data = [
                "id" => $app["id"],
                "sign" => "sdfhjiudfyiunmfhu"
            ];
            $async = $this->http_request("http://35.241.123.37:85/api/push_app", $post_data);
            $result_async = json_decode($async, true);
            if (empty($result_async["code"]) || $result_async["code"] != 200) {
                $this->error("推送失败，请稍后重试", $result_async);
            } else {
                $this->success("推送APP任务已提交");
            }
        } else {
            $this->error("推送失败，数据不存在");
        }
    }

    /**
     * 将APP下载推送到所有token有效用户
     * 用户A下载了应用A和应用B，然后应用C点推送也推给用户A
     * 分国内IP和所有用户
     * 用户更新时间判断1年内的用户
     */
    public function push_one_app($ids = null)
    {
        if (empty($ids)) $this->error("缺少参数，请刷新重试");
        if ($this->request->isPost()) {
            //处理传递参数
            $post = $this->request->post("row/a");
            if (empty($post['efficient_time'])) $this->error("请选择推送时间范围");
            $efficientTime = explode(' - ', $post['efficient_time']);
            $post['max_push_num'] = intval(($post['max_push_num'] ?? 0)) == 0 ? 0 : $post['max_push_num'];
            $app = $this->model->where("id", $ids)->find();
            if ($app) {
                $post_data = [
                    'id' => $ids,
                    'sign' => 'sdfhjiudfyiunmfhu',
                    'start_time' => $efficientTime[0],
                    'end_time' => $efficientTime[1],
                    'max_push_num' => $post['max_push_num'],
                    'ip_country' => $post['ip_country'] ?? 0
                ];
                $async = $this->http_request("http://35.241.123.37:85/api/push_one_app", $post_data);
                $result_async = json_decode($async, true);
                if (empty($result_async["code"]) || $result_async["code"] != 200) {
                    $this->error("推送失败，请稍后重试", $result_async);
                } else {
                    $this->success("推送APP任务已提交");
                }
            } else {
                $this->error("推送失败，数据不存在");
            }
        }
        return $this->view->fetch();
    }

    /**
     * 生成查询所需要的条件,排序方式
     * @param mixed   $searchfields   快速查询的字段
     * @param boolean $relationSearch 是否关联查询
     * @return array
     */
    protected function buildparams($searchfields = null, $relationSearch = null)
    {
        $searchfields = is_null($searchfields) ? $this->searchFields : $searchfields;
        $relationSearch = is_null($relationSearch) ? $this->relationSearch : $relationSearch;
        $search = $this->request->get("search", '');
        $filter = $this->request->get("filter", '');
        $op = $this->request->get("op", '', 'trim');
        $sort = $this->request->get("sort", "id");
        $order = $this->request->get("order", "DESC");
        $offset = $this->request->get("offset", 0);
        $limit = $this->request->get("limit", 0);
        $filter = (array)json_decode($filter, true);
        $op = (array)json_decode($op, true);
        $filter = $filter ? $filter : [];
        if (array_key_exists("auto_app_refush.status", $op)) {
            unset($op["auto_app_refush.status"]);
            unset($filter["auto_app_refush.status"]);
        }
        $where = [];
        $tableName = '';
        if ($relationSearch) {
            if (!empty($this->model)) {
                $name = \think\Loader::parseName(basename(str_replace('\\', '/', get_class($this->model))));
                $tableName = $name . '.';
            }
            $sortArr = explode(',', $sort);
            foreach ($sortArr as $index => &$item) {
                $item = stripos($item, ".") === false ? $tableName . trim($item) : $item;
            }
            unset($item);
            $sort = implode(',', $sortArr);
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $where[] = [$tableName . $this->dataLimitField, 'in', $adminIds];
        }
        if ($search) {
            $searcharr = is_array($searchfields) ? $searchfields : explode(',', $searchfields);
            foreach ($searcharr as $k => &$v) {
                $v = stripos($v, ".") === false ? $tableName . $v : $v;
            }
            unset($v);
            $where[] = [implode("|", $searcharr), "LIKE", "%{$search}%"];
        }
        foreach ($filter as $k => $v) {
            $sym = isset($op[$k]) ? $op[$k] : '=';
            if (stripos($k, ".") === false) {
                $k = $tableName . $k;
            }
            $v = !is_array($v) ? trim($v) : $v;
            $sym = strtoupper(isset($op[$k]) ? $op[$k] : $sym);
            switch ($sym) {
                case '=':
                case '<>':
                    $where[] = [$k, $sym, (string)$v];
                    break;
                case 'LIKE':
                case 'NOT LIKE':
                case 'LIKE %...%':
                case 'NOT LIKE %...%':
                    $where[] = [$k, trim(str_replace('%...%', '', $sym)), "%{$v}%"];
                    break;
                case '>':
                case '>=':
                case '<':
                case '<=':
                    $where[] = [$k, $sym, intval($v)];
                    break;
                case 'FINDIN':
                case 'FINDINSET':
                case 'FIND_IN_SET':
                    $where[] = "FIND_IN_SET('{$v}', " . ($relationSearch ? $k : '`' . str_replace('.', '`.`', $k) . '`') . ")";
                    break;
                case 'IN':
                case 'IN(...)':
                case 'NOT IN':
                case 'NOT IN(...)':
                    $where[] = [$k, str_replace('(...)', '', $sym), is_array($v) ? $v : explode(',', $v)];
                    break;
                case 'BETWEEN':
                case 'NOT BETWEEN':
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $sym = $sym == 'BETWEEN' ? '<=' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $sym = $sym == 'BETWEEN' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $where[] = [$k, $sym, $arr];
                    break;
                case 'RANGE':
                case 'NOT RANGE':
                    $v = str_replace(' - ', ',', $v);
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $sym = $sym == 'RANGE' ? '<=' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $sym = $sym == 'RANGE' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $where[] = [$k, str_replace('RANGE', 'BETWEEN', $sym) . ' time', $arr];
                    break;
                case 'LIKE':
                case 'LIKE %...%':
                    $where[] = [$k, 'LIKE', "%{$v}%"];
                    break;
                case 'NULL':
                case 'IS NULL':
                case 'NOT NULL':
                case 'IS NOT NULL':
                    $where[] = [$k, strtolower(str_replace('IS ', '', $sym))];
                    break;
                default:
                    break;
            }
        }
        $where = function ($query) use ($where) {
            foreach ($where as $k => $v) {
                if (is_array($v)) {
                    call_user_func_array([$query, 'where'], $v);
                } else {
                    $query->where($v);
                }
            }
        };
        return [$where, $sort, $order, $offset, $limit];
    }


    /***
     * 注入重签
     * @param string $ids
     */
    public function allib($ids = "")
    {
        $app = $this->model->where("id", $ids)
            ->where("is_resign", 0)
            ->find();
        $post = [
            'app_id' => $app['id'],
            "key" => md5($app['id'] . "iossign"),
            "is_resign" => 1
        ];
        $async = $this->http_request("http://8.218.63.79:85/index/alib_app", $post);
        $result_async = json_decode($async, true);
        if (empty($result_async["code"]) || $result_async["code"] != 200) {
            $this->error("注入失败，请稍后重试", $result_async);
        } else {
            $this->success("注入任务已提交");
        }
    }

    /**
     * 关闭重签
     * @param string $ids
     */
    public function dllib($ids = "")
    {
        $app = $this->model->where("id", $ids)
            ->where("is_resign", 1)
            ->find();
        $post = [
            'app_id' => $app['id'],
            "key" => md5($app['id'] . "iossign"),
            "is_resign" => 0
        ];
        $async = $this->http_request("http://8.218.63.79:85/index/alib_app", $post);
        $result_async = json_decode($async, true);
        if (empty($result_async["code"]) || $result_async["code"] != 200) {
            $this->error("注入失败，请稍后重试", $result_async);
        } else {
            $this->success("注入任务已提交");
        }
    }


    /***
     * 同步到google
     * @param string $ids
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function oss_to_google($ids = "")
    {
        $app = $this->model->where("id", $ids)->find();
        /**OSS分流***/
        $user = User::where("id", $app["user_id"])->find();
        $proxy = ProxyUserDomain::where(['user_id' => $user['pid']])->find();
        if ($proxy["oss_id"]) {
            $async_oss_config = OssConfig::where("id", $proxy["oss_id"])
                ->find();
        } else {
            $async_oss_config = null;
        }
        if ($async_oss_config) {
            $oss_id = $async_oss_config["id"];
        } else {
            $oss_id = 0;
        }
        /**
         * @todo 谷歌同步
         */
        $post_google = [
            "oss_path" => $app["oss_path"],
            "sign" => strtoupper(md5($app["oss_path"] . "kiopmwhyusn")),
            "oss_id" => $oss_id,
            "async_oss_config" => $async_oss_config,
        ];
        $result = $this->http_request("http://35.227.214.161/index/oss_to_google", $post_google);
        $result_async = json_decode($result, true);
        if (empty($result_async["code"]) || $result_async["code"] != 200) {
            $this->error("google同步任务失败，请稍后再试", $result_async);
        } else {
            if (!empty($app["apk_url"])) {
                if (strstr($app['apk_url'], "http")) {
                } else {
                    $post_apk_google = [
                        "oss_path" => $app["apk_url"],
                        "sign" => strtoupper(md5($app["apk_url"] . "kiopmwhyusn")),
                        "oss_id" => $oss_id,
                        "async_oss_config" => $async_oss_config,
                    ];
                    $result = $this->http_request("http://35.227.214.161/index/oss_to_google", $post_apk_google);
                    $result_async = json_decode($result, true);
                }
            }
            $this->success("google同步任务已提交");
        }
    }

    public function google_to_oss($ids = "")
    {
        $app = $this->model->where("id", $ids)->find();
        /**OSS分流***/
        $user = User::where("id", $app["user_id"])->find();
        $proxy = ProxyUserDomain::where(['user_id' => $user['pid']])->find();
        if ($proxy["oss_id"]) {
            $async_oss_config = OssConfig::where("id", $proxy["oss_id"])
                ->find();
        } else {
            $async_oss_config = null;
        }
        if ($async_oss_config) {
            $oss_id = $async_oss_config["id"];
        } else {
            $oss_id = 0;
        }
        /**
         * @todo 谷歌同步
         */
        $post_google = [
            "oss_path" => $app["oss_path"],
            "sign" => strtoupper(md5($app["oss_path"] . "kiopmwhyusn")),
            "oss_id" => $oss_id,
            "async_oss_config" => $async_oss_config,
        ];
        $result = $this->http_request("http://35.227.214.161/index/google_to_oss", $post_google);
        $result_async = json_decode($result, true);
        if (empty($result_async["code"]) || $result_async["code"] != 200) {
            $this->error("ali同步任务失败，请稍后再试", $result_async);
        } else {
            if (!empty($app["apk_url"])) {
                if (strstr($app['apk_url'], "http")) {
                } else {
                    $post_apk_google = [
                        "oss_path" => $app["apk_url"],
                        "sign" => strtoupper(md5($app["apk_url"] . "kiopmwhyusn")),
                        "oss_id" => $oss_id,
                        "async_oss_config" => $async_oss_config,
                    ];
                    $result = $this->http_request("http://35.227.214.161/index/google_to_oss", $post_apk_google);
                    $result_async = json_decode($result, true);
                }
            }
            $this->success("ali同步任务已提交");
        }
    }

    public function g_google_to_oss($ids = "")
    {
        $app = $this->model->where("id", $ids)->find();
        /**OSS分流***/
        $user = User::where("id", $app["user_id"])->find();
        $proxy = ProxyUserDomain::where(['user_id' => $user['pid']])->find();
        if ($proxy["oss_id"]) {
            $async_oss_config = OssConfig::where("id", $proxy["oss_id"])
                ->find();
        } else {
            $async_oss_config = null;
        }
        if ($async_oss_config) {
            $oss_id = $async_oss_config["id"];
        } else {
            $oss_id = 0;
        }
        /**
         * @todo 谷歌同步
         */
        $post_google = [
            "oss_path" => $app["oss_path"],
            "sign" => strtoupper(md5($app["oss_path"] . "kiopmwhyusn")),
            "oss_id" => $oss_id,
            "async_oss_config" => $async_oss_config,
        ];
        $result = $this->http_request("http://35.227.214.161/index/g_oss_to_google", $post_google);
        $result_async = json_decode($result, true);
        if (empty($result_async["code"]) || $result_async["code"] != 200) {
            $this->error("ali同步任务失败，请稍后再试", $result_async);
        } else {
            if (!empty($app["apk_url"])) {
                if (strstr($app['apk_url'], "http")) {
                } else {
                    $post_apk_google = [
                        "oss_path" => $app["apk_url"],
                        "sign" => strtoupper(md5($app["apk_url"] . "kiopmwhyusn")),
                        "oss_id" => $oss_id,
                        "async_oss_config" => $async_oss_config,
                    ];
                    $result = $this->http_request("http://35.227.214.161/index/g_oss_to_google", $post_apk_google);
                    $result_async = json_decode($result, true);
                }
            }
            $this->success("ali同步任务已提交");
        }
    }



    protected function clear_app_cache($ids)
    {
        $list = \app\admin\model\ProxyApp::whereIn("id", $ids)->column("id,tag,short_url");
        foreach ($list as $v) {
            $redis = new Redis(["select" => 4]);
            $redis->handle()->del("app_tag:" . $v["tag"]);
            $redis->handle()->del("app_short_url:" . $v["short_url"]);
            $redis->handle()->close();
            $redis = null;
        }
        return true;
    }

    public function check_update($ids = null)
    {
        if (empty($ids)) {
            $this->error("缺少参数，请刷新重试");
        }
        $app = $this->model->where("id", $ids)->find();
        if ($this->request->isPost()) {
            if ($app["is_update"] != 1) {
                $this->error("该应用无更新，请刷新重试");
            }

            $update = json_decode($app["update_data"], true);
            if (!isset($update["bundle_name"])) {
                $this->error("请重新上传应用");
            }
            $update_data = [
                'id' => $app["id"],
                'bundle_name' => $update["bundle_name"],
                "is_update" => 0
            ];
            if ($app["is_apiSign"] == 1) {
                //星空
                $this->xingkong_sign($ids);
            } else {
            if ($update["is_overseas"] == 10) {
                $sign_url = "120.77.83.86:85";
            } else {
                $sign_url = "35.227.214.161";
                //                $sign_url = "8.218.75.38:85";
            }
            $post_data = $update["ansyc_data"];
            if (!empty($post_data["async_oss_config"])) {
                $post_data["async_oss_config"] = json_encode($post_data["async_oss_config"]);
            }
            $async = $this->http_request("http://$sign_url/index/alib_int_app", $post_data);
            $result_async = json_decode($async, true);
            if (empty($result_async["code"]) || $result_async["code"] != 200) {
                $this->error("更新失败，请刷新重试");
            }
            if (\app\admin\model\ProxyApp::update($update_data)) {
                $is_exit = TestApp::where("app_id", $app["id"])->find();
                if ($is_exit) {
                    $this->model->where("id", $is_exit["test_app_id"])->delete();
                    TestApp::where("id", $is_exit["id"])->delete();
                }
                $redis = new Redis(["select" => 4]);
                $redis->handle()->del("app_tag:" . $app["tag"]);
                $redis->handle()->del("app_short_url:" . $app["short_url"]);
                $redis->handle()->close();
                /**签名开始***/
                $redis_check = new Redis(["select" => 8]);
                $redis_check->handle()->set("sign_app_loading:" . $app["id"], $app["tag"], 600);
                $redis_check->handle()->close();
                $this->success("更新审核成功");
            } else {
                $this->error("更新失败，请刷新重试");
            }
        }
        }
        $public_url =  Config::where('name', 'proxy_en_oss_public_url')->value('value');
        $update_log = ProxyAppUpdateLog::where("app_id", $app["id"])
            ->order("create_time", "DESC")
            ->column("id,name,icon,package_name,version_code,bundle_name,filesize,create_time");
        foreach ($update_log as $k => $v) {
            $update_log[$k]['filesize'] = format_bytes($v['filesize']);
            $update_log[$k]["icon"] = $public_url . "/" . substr($v["icon"], strpos($v["icon"], 'upload/'));
        }
        $app["icon"] = $public_url . "/" . substr($app["icon"], strpos($app["icon"], 'upload/'));
        $app["filesize"] = format_bytes($app['filesize']);

        $this->assign("update_log",  array_values($update_log));
        $this->assign("app", $app);
        return $this->view->fetch();
    }

    public function check_add($ids = null)
    {
        if (empty($ids)) {
            $this->error("缺少参数，请刷新重试");
        }
        $app = $this->model->where("id", $ids)->find();
        if ($this->request->isPost()) {
            if ($app["is_add"] != 1) {
                $this->error("该应用无更新，请刷新重试");
            }

            $update = json_decode($app["add_data"], true);
            $update_data = [
                'id' => $app["id"],
                "is_add" => 0
            ];
            $user = ProxyUser::get(["id" => $app["user_id"]]);
            /**OSS分流***/
            $proxy = ProxyUserDomain::where(['user_id' => $user['pid']])->find();
            if ($proxy["oss_id"]) {
                $async_oss_config = OssConfig::where("id", $proxy["oss_id"])
                    ->find();
            } else {
                $async_oss_config = null;
                $proxy["oss_id"] = 0;
            }
            $post_data = $update["ansyc_data"];
            $post_data["oss_id"] = $proxy["oss_id"];
            $post_data["app_id"] = $app["id"];
            $post_data["async_oss_config"] = $async_oss_config;
            if ($app["is_apiSign"] == 1) {
                //星空
                $this->xingkong_sign($ids);
            } else {
            if ($update["is_overseas"] == 10) {
                $sign_url = "120.77.83.86:85";
            } else {
                $sign_url = "35.227.214.161";
                //                $sign_url = "8.218.75.38:85";
            }
            $async = $this->http_request("http://$sign_url/index/alib_int_app", $post_data);
            $result_async = json_decode($async, true);
            if (empty($result_async["code"]) || $result_async["code"] != 200) {
                $this->error("更新失败，请刷新重试", '', $async);
            }
            if (\app\admin\model\ProxyApp::update($update_data)) {
                $is_exit = TestApp::where("app_id", $app["id"])->find();
                if ($is_exit) {
                    $this->model->where("id", $is_exit["test_app_id"])->delete();
                    TestApp::where("id", $is_exit["id"])->delete();
                }
                $redis = new Redis(["select" => 4]);
                $redis->handle()->del("app_tag:" . $app["tag"]);
                $redis->handle()->del("app_short_url:" . $app["short_url"]);
                $redis->handle()->close();
                /**签名开始***/
                $redis_check = new Redis(["select" => 8]);
                $redis_check->handle()->set("sign_app_loading:" . $app["id"], $app["tag"], 600);
                $redis_check->handle()->close();
                $this->success("新增审核成功");
            } else {
                $this->error("更新失败，请刷新重试");
            }
            }
        }
        $public_url =  Config::where('name', 'proxy_en_oss_public_url')->value('value');
        $app["icon"] = $public_url . "/" . substr($app["icon"], strpos($app["icon"], 'upload/'));
        $app["filesize"] = format_bytes($app['filesize']);
        $this->assign("app", $app);
        return $this->view->fetch();
    }

    public function ipa_parsing($ids)
    {
        $app = $this->model->where("id", $ids)->find();
        $d_data = json_decode(stripslashes($app["update_data"]), true);
        if ($d_data["is_overseas"] == 20) {
            $host = "35.227.214.161";
        } else {
            $host = "120.77.83.86:85";
        }
        $url = "http://" . $host . "/index/ipa_admin_parsing";
        $path = $d_data["ansyc_data"]["path"];
        $post_data = [
            'oss_path' => $path,
            'user_id' => $app["user_id"]
        ];
        $result = $this->http_request($url, $post_data);
        $data = json_decode($result, true);
        if ($data["result"]) {
            $data["result"]["filesize"] = format_bytes($data["result"]["filesize"]);
            return json(["code" => 1, "data" => $data["result"]]);
        } else {
            return json(["code" => 0, "data" => $data["result"], "msg" => "应用无更新"]);
        }
    }

    /**
     * 应用测试
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function  test_ipa()
    {
        $id = $this->request->param("ids");
        $type = $this->request->param("type");
        $app = $this->model->where("id", $id)->find();
        if (empty($app)) {
            $this->error("暂未查询到应用，请刷新重试");
        }
        $proxy = ProxyUserDomain::get(['user_id' => 2]);
        $is_exit = TestApp::where("app_id", $app["id"])->find();
        if ($is_exit) {
            $test_app_data = $this->model->where("id", $is_exit["test_app_id"])->find();
        } else {
            $test_app_data = null;
        }
        if ($type == "update") {
            $cache_update = json_decode($app["update_data"], true);
        } else {
            $cache_update = json_decode($app["add_data"], true);
        }
        $post_google = [
            "oss_path" => $app["path"],
            "sign" => strtoupper(md5($app["path"] . "kiopmwhyusn")),
            "oss_id" => 0,
            "async_oss_config" => "",
            "is_overseas" => $cache_update["is_overseas"]
        ];
        $sign_url = "http://35.227.214.161/index/test_ipa_tongbu";
        $result = $this->http_request($sign_url, $post_google);

        $acconut_id = Config::where("name", "test_app_cert_id")->value("value");
        if (!empty($test_app_data)) {
            $update = [
                'name' => $app["name"],
                'path' => $app['path'],
                'package_name' => $app['package_name'],
                'version_code' => $app['version_code'],
                'bundle_name' => $app['bundle_name'],
                'oss_path' =>  $app['path'],
                'account_id' => $acconut_id,
                'is_resign' => 1,
                'status' => 1,
                'is_delete' => 1,
                'is_update' => 0,
                'is_download' => 0,
                "update_time" => date("Y-m-d H:i:s")
            ];
            if ($this->model->where("id", $test_app_data["id"])->update($update)) {
                $this->clear_app_cache($test_app_data["id"]);
                //$url = "https://".$proxy["download_url"]."/".$test_app_data["short_url"].".app";
                $url = "https://" . $proxy["download_url"] . "/" . $test_app_data["short_url"];
                $this->success("success", "", ["url" => $url]);
            } else {
                $this->error(__('生成失败,请点击重试  update'));
            }
        } else {
            $tag = uniqid() . rand(111, 999);
            $short_url = Random::alnum(3) . rand(111, 999);
            $is_short_url = $this->model->where('short_url', $short_url)->value('id');
            if ($is_short_url) {
                $short_url = Random::alnum(3) . rand(111, 999);
                $is_short_url = $this->model->where('short_url', $short_url)->value('id');
                if ($is_short_url) {
                    $this->error(__('请点击重试'));
                }
            }
            $data = [
                'name' => $app["name"],
                'path' => $app['path'],
                'tag' => $tag,
                'user_id' => 6,
                'icon' => $app["icon"],
                'ipa_data_bak' => $app['ipa_data_bak'],
                'package_name' => $app['package_name'],
                'version_code' => $app['version_code'],
                'bundle_name' => $app['bundle_name'],
                'account_id' => $acconut_id,
                'status' => 1,
                'filesize' => $app['filesize'],
                'desc' => "测试专用",
                'score_num' => 0,
                'introduction' => "测试专用",
                'oss_path' => $app['path'],
                'apk_url' => '',
                'create_time' => date('Y-m-d H:i:s'),
                'download_limit' => 0,
                'remark' => '',
                'is_vaptcha' => 0,
                'short_url' => $short_url,
                'is_st' => 0,
                'lang' => "zh",
                'comment' => "",
                'comment_name' => "",
                'mode' => 2,
                'is_stop' => 0,
                'is_resign' => 1,
                'is_update' => 0,
                'is_download' => 0,
            ];
            $new_app = \app\admin\model\ProxyApp::create($data);
            if ($new_app) {
                $test_app = [
                    "app_id" => $app["id"],
                    "test_app_id" => $new_app["id"],
                    "create_time" => date("Y-m-d H:i:s")
                ];
                TestApp::create($test_app);
                //$url = "https://" . $proxy["download_url"] . "/" . $short_url . ".app";
                $url = "https://" . $proxy["download_url"] . "/" . $short_url;
                $this->success("success", "", ["url" => $url]);
            }
            $this->error(__('生成失败,请点击重试'));
        }
    }

    /**
     * 测试应用闪退
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function remove_test_install()
    {
        $id = $this->request->param("ids");
        $app = $this->model->where("id", $id)->find();
        if (empty($app)) {
            $this->error("暂未查询到应用，请刷新重试");
        }
        $is_exit = TestApp::where("app_id", $app["id"])->find();
        if ($is_exit) {
            $test_app_data = $this->model->where("id", $is_exit["test_app_id"])->find();
            if ($test_app_data) {
                if ($this->model->where("id", $test_app_data["id"])->update(["status" => -1])) {
                    $this->clear_app_cache($test_app_data["id"]);
                    $this->success("清除缓存成功，清重新打开应用查看是否闪退");
                }
            }
        }
        $this->error("信息错误，请刷新重试");
    }


    public function upload_st_moblieconfig($ids = '')
    {
        $app_id = $ids;
        $app = $this->model->where("id", $app_id)->find();
        $ip2 = new Ip2Region();
        $ip_address = $ip2->binarySearch($this->request->ip());
        $address = explode('|', $ip_address['region']);
        if ($address[0] == "中国" && !in_array($address[2], ["澳门", '香港', "台湾省"])) {
            $public_url =  Config::where('name', 'proxy_zh_oss_public_url')->value('value');
        } else {
            $public_url =  Config::where('name', 'proxy_en_oss_public_url')->value('value');
        }
        $app["icon"] = $public_url . "/" . substr($app["icon"], strpos($app["icon"], 'upload/'));
        if ($this->request->isPost()) {
            $post = $this->request->post("row/a");
            $status = $post['status'];
            $app['custom_st'] = $status;
            if ($status === "1") {
                $st_name = $post['name'];
                $st_url = $post["url"];

                // 37下载
                $url = "https://39zhp.cc/index/get_customer_st";
                // 146下载
                $url_146 = "https://f710z.cc/index/get_customer_st";
                // 247下载
                $url_247 =  "https://j0pyo.cc/index/get_customer_st";

                $url_120 = "https://2s4ci.cc/index/get_customer_st";

                $post_data = [
                    'name' => $st_name,
                    'url' => $st_url,
                    'uuid' => $app['short_url'],
                    'icon_url' => $app['icon'],
                ];
                $result = $this->http_request($url, $post_data);
                $text = $result;
                $result = json_decode($result, true);
                if ($result["code"] == 200) {
                    $app['custom_st_url'] = $result['data']['mobileconfig'];
                    $app['st_url'] = $st_url;
                    $app['custom_st_name'] = $st_name;
                    $app->save();
                } else
                    $this->error("设置失败1" . $text);



                $result = $this->http_request($url_146, $post_data);
                $text = $result;
                $result = json_decode($result, true);
                if ($result["code"] != 200) {
                    $this->error("设置失败2" . $text);
                }

                $result = $this->http_request($url_247, $post_data);
                $text = $result;
                $result = json_decode($result, true);
                if ($result["code"] != 200) {
                    $this->error("设置失败3" . $text);
                }

                $result = $this->http_request($url_120, $post_data);
                $text = $result;
                $result = json_decode($result, true);
                if ($result["code"] != 200) {
                    $this->error("设置失败4" . $text);
                }

                $this->success("设置成功");
            }


            $app->save();
            $this->success("关闭成功");
        }
        if(empty($app->custom_st_name))
        {
            $app->custom_st_name = $app->name . "防闪退";
        }
        $this->assign("app", $app);
        return $this->view->fetch();
    }
}
