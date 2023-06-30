<?php

namespace app\admin\controller;

use app\admin\model\Enterprise as EnterpriseModel;
use app\admin\model\OssConfig;
use app\common\controller\Backend;
use app\common\library\Oss;
use think\Db;
use think\Exception;
use think\exception\PDOException;

/**
 * 企业证书保存列管理
 *
 * @icon fa fa-circle-o
 */
class Enterprise extends Backend
{

    /**
     * Enterprise模型对象
     * @var EnterpriseModel
     */
    protected $model = null;
    protected $multiFields='status';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new EnterpriseModel;

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
                if (!ctype_alnum($params['name'])) {
                    $this->error('名称错误');
                }
                $is_check = $this->model->where('name', $params['name'])->value('id');
                if ($is_check) {
                    $this->error('名称已存在');
                }
                $cert = ROOT_PATH . '/public/' . $params['path'];
                $prov = ROOT_PATH . '/public/' . $params['provisioning_path'];
                if (!is_file($cert) || !is_file($prov)) {
                    $this->error('请上传文件');
                }
                $data = [
                    'name' => $params['name'],
                    'path' => $params['path'],
                    'password' => $params['password'],
                    'provisioning_path' => $params['provisioning_path'],
                    'status' => $params['status'],
                    'create_time' => date('Y-m-d H:i:s'),
                ];
                $cert_result = $prov_result = true;
                $oss_config=OssConfig::where("name","oss")
                    ->where("status",1)
                    ->cache(true,10*60)
                    ->find();
                $oss = new Oss($oss_config);
                $oss_g_config=OssConfig::where("name","g_oss")
                    ->where("status",1)
                    ->cache(true,10*60)
                    ->find();
                $oss_g = new Oss($oss_g_config);
                $cert_path = 'uploads/' . date('Ymd') . '/' . $params['name'] . '.p12';
                $prov_path = 'uploads/' . date('Ymd') . '/' . $params['name'] . '.mobileprovision';
                $cert_result = $oss->ossUpload($cert, $cert_path);
                $prov_result = $oss->ossUpload($prov, $prov_path);
                $oss_g->ossUpload($cert, $cert_path);
                $oss_g->ossUpload($prov, $prov_path);
                $data['oss_path'] = $cert_path;
                $data['oss_provisioning'] = $prov_path;

                if ($cert_result && $prov_result) {
                    if(!unlink($cert)||!unlink($prov)){
                        $this->error('添加失败');
                    }
                    if ($this->model->insert($data)) {  
                        $this->success('添加成功');
                    } else {
                        $this->error('添加失败');
                    }
                } else {
                    $this->error('上传失败');
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

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
                $cert = ROOT_PATH . '/public/' . $params['path'];
                $prov = ROOT_PATH . '/public/' . $params['provisioning_path'];
                if (!is_file($cert) || !is_file($prov)) {
                    $this->error('请上传文件');
                }
                $data = [
                    'id' => $params['id'],
                    'path' => $params['path'],
                    'password' => $params['password'],
                    'provisioning_path' => $params['provisioning_path'],
                    'status' => $params['status'],
                ];
                $cert_result = $prov_result = true;
//                if (config('is_oss')) {
                    $oss_config=OssConfig::where("name","oss")
                        ->where("status",1)
                        ->cache(true,10*60)
                        ->find();
                    $oss = new Oss($oss_config);
                    $oss_g_config=OssConfig::where("name","g_oss")
                        ->where("status",1)
                        ->cache(true,10*60)
                        ->find();
                    $oss_g = new Oss($oss_g_config);
                    $cert_path = 'uploads/' . date('Ymd') . '/' . $params['name'] . '.p12';
                    $prov_path = 'uploads/' . date('Ymd') . '/' . $params['name'] . '.mobileprovision';
                    $cert_result = $oss->ossUpload($cert, $cert_path);
                    $prov_result = $oss->ossUpload($prov, $prov_path);
                    $oss_g->ossUpload($cert, $cert_path);
                    $oss_g->ossUpload($prov, $prov_path);
                    $data['oss_path'] = $cert_path;
                    $data['oss_provisioning'] = $prov_path;
//                }
                if ($cert_result && $prov_result) {
                    if (EnterpriseModel::update($data)) {
                        $this->success();
                    } else {
                        $this->error(__('No rows were updated'));
                    }
                } else {
                    $this->error('上传失败');
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
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
            $oss = new Oss();
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    if (!empty($v['oss_path'])) {
                        $oss->ossDelete($v['oss_path']);
                    }
                    if (!empty($v['oss_provisioning'])) {
                        $oss->ossDelete($v['oss_provisioning']);
                    }
                    if (is_file($v['path'])) {
                        unlink(ROOT_PATH . '/public' . $v['path']);
                    }
                    if (is_file($v['provisioning_path'])) {
                        unlink(ROOT_PATH . '/public' . $v['provisioning_path']);
                    }
                    $count += $v->delete();
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


}
