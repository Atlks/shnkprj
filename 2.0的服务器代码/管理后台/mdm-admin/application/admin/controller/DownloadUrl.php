<?php

namespace app\admin\controller;

use app\admin\model\OssConfig;
use app\common\controller\Backend;
use app\common\library\Oss;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 下载域名管理
 *
 * @icon fa fa-circle-o
 */
class DownloadUrl extends Backend
{
    
    /**
     * DownloadUrl模型对象
     * @var \app\admin\model\DownloadUrl
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\DownloadUrl;

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
                if(!strpos($params['cert_path'],'.crt')){
                    $this->error('请上传CRT证书');
                }
                if(!strpos($params['pem_path'],'.pem')){
                    $this->error('请上传PEM证书');
                }
                if(!strpos($params['key_path'],'.key')){
                    $this->error('请上传KEY证书');
                }
                $cert_path = 'dailicert/'.date('Y-m-d').'/'.pathinfo($params['cert_path'])['basename'];
                $pem_path = 'dailicert/'.date('Y-m-d').'/'.pathinfo($params['pem_path'])['basename'];
                $key_path = 'dailicert/'.date('Y-m-d').'/'.pathinfo($params['key_path'])['basename'];
                $oss_config=OssConfig::where("name","g_oss")
                    ->where("status",1)
                    ->cache(true,10*60)
                    ->find();
                $oss = new Oss($oss_config);

                $oss_zh_config=OssConfig::where("name","oss")
                    ->where("status",1)
                    ->cache(true,10*60)
                    ->find();
                $oss_zh = new Oss($oss_zh_config);
                if(is_file(ROOT_PATH.'/public'.$params['cert_path'])){
                    if(!$oss->ossUpload(ROOT_PATH.'/public'.$params['cert_path'],$cert_path)){
                        $this->error('添加失败');
                    }
                    $oss_zh->ossUpload(ROOT_PATH.'/public'.$params['cert_path'],$cert_path);
                    $params['cert_path']=$cert_path;
                }
                if(is_file(ROOT_PATH.'/public'.$params['pem_path'])){
                    if(!$oss->ossUpload(ROOT_PATH.'/public'.$params['pem_path'],$pem_path)){
                        $this->error('添加失败');
                    }
                    $oss_zh->ossUpload(ROOT_PATH.'/public'.$params['pem_path'],$pem_path);
                    $params['pem_path']=$pem_path;
                }
                if(is_file(ROOT_PATH.'/public'.$params['key_path'])){
                    if(!$oss->ossUpload(ROOT_PATH.'/public'.$params['key_path'],$key_path)){
                        $this->error('添加失败');
                    }
                    $oss_zh->ossUpload(ROOT_PATH.'/public'.$params['key_path'],$key_path);
                    $params['key_path']=$key_path;
                }
                $params["create_time"]=date('Y-m-d H:i:s');

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
                if(!strpos($params['cert_path'],'.crt')){
                    $this->error('请上传CRT证书');
                }
                if(!strpos($params['pem_path'],'.pem')){
                    $this->error('请上传PEM证书');
                }
                if(!strpos($params['key_path'],'.key')){
                    $this->error('请上传KEY证书');
                }
                $cert_path = 'dailicert/'.date('Y-m-d').'/'.pathinfo($params['cert_path'])['basename'];
                $pem_path = 'dailicert/'.date('Y-m-d').'/'.pathinfo($params['pem_path'])['basename'];
                $key_path = 'dailicert/'.date('Y-m-d').'/'.pathinfo($params['key_path'])['basename'];
                $oss_config=OssConfig::where("name","g_oss")
                    ->where("status",1)
                    ->cache(true,10*60)
                    ->find();
                $oss = new Oss($oss_config);
                $oss_zh_config=OssConfig::where("name","oss")
                    ->where("status",1)
                    ->cache(true,10*60)
                    ->find();
                $oss_zh = new Oss($oss_zh_config);
                if(is_file(ROOT_PATH.'/public'.$params['cert_path'])){
                    if(!$oss->ossUpload(ROOT_PATH.'/public'.$params['cert_path'],$cert_path)){
                        $this->error('添加失败');
                    }
                    $oss_zh->ossUpload(ROOT_PATH.'/public'.$params['cert_path'],$cert_path);
                    $params['cert_path']=$cert_path;
                }
                if(is_file(ROOT_PATH.'/public'.$params['pem_path'])){
                    if(!$oss->ossUpload(ROOT_PATH.'/public'.$params['pem_path'],$pem_path)){
                        $this->error('添加失败');
                    }
                    $oss_zh->ossUpload(ROOT_PATH.'/public'.$params['pem_path'],$pem_path);
                    $params['pem_path']=$pem_path;
                }
                if(is_file(ROOT_PATH.'/public'.$params['key_path'])){
                    if(!$oss->ossUpload(ROOT_PATH.'/public'.$params['key_path'],$key_path)){
                        $this->error('添加失败');
                    }
                    $oss_zh->ossUpload(ROOT_PATH.'/public'.$params['key_path'],$key_path);
                    $params['key_path']=$key_path;
                }
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
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }


}
