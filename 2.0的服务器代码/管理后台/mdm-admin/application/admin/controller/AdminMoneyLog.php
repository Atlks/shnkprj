<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\model\ProxyUser;
use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\Loader;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class AdminMoneyLog extends Backend
{
    
    /**
     * AdminMoneyLog模型对象
     * @var \app\admin\model\AdminMoneyLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\AdminMoneyLog;

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
            $filter = $this->request->get("filter", '');
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            /**商务对账***/
            $groupid = $this->auth->getGroupIds($this->auth->id);
            if($this->auth->isSuperAdmin()||$this->auth->id=="10"||in_array(23,$groupid)||in_array(24,$groupid)){
                $admin_where=[];
            }else{
                $admin_where=[
                    "admin_money_log.create_admin_id"=>$this->auth->id
                ];
            }
            $admin = new Admin();
            $user = new ProxyUser();
            $filter = (array)json_decode($filter, true);
            $filter = $filter ? $filter : [];
            if(isset($filter["user_name"])&& !empty($filter["user_name"])){
                $user_id = $user->where("username",$filter["user_name"])->value("id");
                $admin_id = $admin->where("username",$filter["user_name"])->value("id");
                $user_ids = array_filter([$user_id,$admin_id]);
                $admin_where=[
                    "admin_money_log.user_id"=>["IN",$user_ids]
                ];
            }
            $list = $this->model
                    ->with(['admin'])
                    ->where($where)
                    ->where($admin_where)
                    ->order($sort, $order)
                    ->paginate($limit);
            foreach ($list as $row) {
                if(in_array($row["type"],[1,2,5])){
                    $row["user_name"] = $admin->where("id",$row["user_id"])->value("username");
                }else{
                    $row["user_name"] = $user->where("id",$row["user_id"])->value("username");
                }
                $row->getRelation('admin')->visible(['username']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
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
        $admin = new Admin();
        $user = new ProxyUser();
        if(in_array($row["type"],[1,2])){
            $row["user_name"] = $admin->where("id",$row["user_id"])->value("username");
        }else{
            $row["user_name"] = $user->where("id",$row["user_id"])->value("username");
        }
        $this->view->assign("row", $row);
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
        $sort = $this->request->get("sort", !empty($this->model) && $this->model->getPk() ? $this->model->getPk() : 'id');
        $order = $this->request->get("order", "DESC");
        $offset = $this->request->get("offset/d", 0);
        $limit = $this->request->get("limit/d", 999999);
        //新增自动计算页码
        $page = $limit ? intval($offset / $limit) + 1 : 1;
        if ($this->request->has("page")) {
            $page = $this->request->get("page/d", 1);
        }
        $this->request->get([config('paginate.var_page') => $page]);
        $filter = (array)json_decode($filter, true);
        $op = (array)json_decode($op, true);
        $filter = $filter ? $filter : [];
        $where = [];
        $alias = [];
        $bind = [];
        $name = '';
        $aliasName = '';
        if(isset($filter["user_name"])){
            unset($filter["user_name"]);
            unset($op["user_name"]);
        }
        if (!empty($this->model) && $this->relationSearch) {
            $name = $this->model->getTable();
            $alias[$name] = Loader::parseName(basename(str_replace('\\', '/', get_class($this->model))));
            $aliasName = $alias[$name] . '.';
        }
        $sortArr = explode(',', $sort);
        foreach ($sortArr as $index => & $item) {
            $item = stripos($item, ".") === false ? $aliasName . trim($item) : $item;
        }
        unset($item);
        $sort = implode(',', $sortArr);
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $where[] = [$aliasName . $this->dataLimitField, 'in', $adminIds];
        }
        if ($search) {
            $searcharr = is_array($searchfields) ? $searchfields : explode(',', $searchfields);
            foreach ($searcharr as $k => &$v) {
                $v = stripos($v, ".") === false ? $aliasName . $v : $v;
            }
            unset($v);
            $where[] = [implode("|", $searcharr), "LIKE", "%{$search}%"];
        }
        $index = 0;
        foreach ($filter as $k => $v) {
            if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $k)) {
                continue;
            }
            $sym = isset($op[$k]) ? $op[$k] : '=';
            if (stripos($k, ".") === false) {
                $k = $aliasName . $k;
            }
            $v = !is_array($v) ? trim($v) : $v;
            $sym = strtoupper(isset($op[$k]) ? $op[$k] : $sym);
            //null和空字符串特殊处理
            if (!is_array($v)) {
                if (in_array(strtoupper($v), ['NULL', 'NOT NULL'])) {
                    $sym = strtoupper($v);
                }
                if (in_array($v, ['""', "''"])) {
                    $v = '';
                    $sym = '=';
                }
            }

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
                    $v = is_array($v) ? $v : explode(',', str_replace(' ', ',', $v));
                    $findArr = array_values($v);
                    foreach ($findArr as $idx => $item) {
                        $bindName = "item_" . $index . "_" . $idx;
                        $bind[$bindName] = $item;
                        $where[] = "FIND_IN_SET(:{$bindName}, `" . str_replace('.', '`.`', $k) . "`)";
                    }
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
                    $tableArr = explode('.', $k);
                    if (count($tableArr) > 1 && $tableArr[0] != $name && !in_array($tableArr[0], $alias) && !empty($this->model)) {
                        //修复关联模型下时间无法搜索的BUG
                        $relation = Loader::parseName($tableArr[0], 1, false);
                        $alias[$this->model->$relation()->getTable()] = $tableArr[0];
                    }
                    $where[] = [$k, str_replace('RANGE', 'BETWEEN', $sym) . ' TIME', $arr];
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
            $index++;
        }
        if (!empty($this->model)) {
            $this->model->alias($alias);
        }
        $model = $this->model;
        $where = function ($query) use ($where, $alias, $bind, &$model) {
            if (!empty($model)) {
                $model->alias($alias);
                $model->bind($bind);
            }
            foreach ($where as $k => $v) {
                if (is_array($v)) {
                    call_user_func_array([$query, 'where'], $v);
                } else {
                    $query->where($v);
                }
            }
        };
        return [$where, $sort, $order, $offset, $limit, $page, $alias, $bind];
    }

}
