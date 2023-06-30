<?php


namespace app\admin\controller;


use app\common\controller\Backend;
use app\common\library\Redis;

/**
 * IDFV清除
 *
 * @icon   fa fa-recycle
 * @remark 用于清除异常IDFV
 */
class ClearUdid extends Backend
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
            if(empty($post["udid"])){
                $this->error('请输入UDID');
            }
            $app_id = \app\admin\model\AppInstallCallback::where("udid",$post["udid"])->column("app_id");
           \app\admin\model\AppInstallCallback::where("udid",$post["udid"])->delete();
            Redis::del("udidToken:".$post["udid"],["select"=>2]);
            foreach ($app_id as $v){
                Redis::del("appInstall_udid:".$v.":".$post["udid"],["select"=>5]);
            }
            $this->success("清除成功");
        }
        return $this->view->fetch();
    }

}