<?php

namespace app\admin\controller;

use app\admin\model\ProxyApp;
use app\admin\model\ProxyUser;
use app\admin\model\ProxyV1BaleRate;
use app\common\controller\Backend;
use think\Db;

/**
 * 打包收费管理
 *
 * @icon fa fa-circle-o
 */
class BaleRateV1 extends Backend
{
    
    /**
     * BaleRate模型对象
     * @var ProxyV1BaleRate
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new ProxyV1BaleRate();

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
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
//            if ($this->request->request('keyField')) {
//                return $this->selectpage();
//            }
            $filter = $this->request->get("filter", '');
            $op = (array)json_decode($this->request->param("op"),true);
            $filter = (array)json_decode($filter, true);
            $time_where=[
                'status'=>1
            ];
            if(empty($op)||!array_key_exists("create_time",$op)){
                $time_where["create_time"] = ["between time",[date("Y-m-d 00:00"),date("Y-m-d 23:59:59")]];
            }
            if(!empty($op)){
              if(array_key_exists("app_name",$filter)&& !empty(trim($filter["app_name"]))){
                  $app_id = ProxyApp::whereLike("name","%".trim($filter["app_name"])."%")->column("id");
                  if(!empty($app_id)){
                      $time_where["app_id"] =["IN",$app_id];
                  }
              }
              if(array_key_exists("username",$filter)&& !empty(trim($filter["username"]))){
                  $user_id = ProxyUser::where("username",trim($filter["username"]))->column("id");
                  if(!empty($user_id)){
                      $time_where["user_id"] =["IN",$user_id];
                  }
              }
            }
            $total = 0;
            $list = [];
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            for ($i=0;$i<10;$i++){
                $table = "proxy_v1_bale_rate_".$i;
                $total += Db::table($table)
                    ->connect("ios_db_config")
                    ->where($where)
                    ->where($time_where)
                    ->count();
                $cache_list = Db::table($table)
                    ->connect("ios_db_config")
                    ->where($where)
                    ->where($time_where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->column("*");
                $list = array_merge($list, $cache_list);
            }
            foreach ($list as $k=>$row) {
                $list[$k]["username"] = ProxyUser::where("id",$row["user_id"])->cache(true,1800)->value("username");
                $list[$k]["app_name"] = ProxyApp::where("id",$row["app_id"])->cache(true,1800)->value("name");
            }
            array_multisort(array_column($list,"create_time"),SORT_DESC,$list);
            $result = array("total" => $total, "rows" => array_values($list),'extend'=>['all_num'=>$total]);

            return json($result);
        }
        $groups = $this->auth->getGroupIds($this->auth->id);
        if(in_array(1,$groups)){
            $visable = true;
            $is_operate = "=";
        }else{
            $visable = false;
            $is_operate=false;
        }
        $this->assignconfig("is_showColumn",$visable);
        $this->assignconfig("is_operate",$is_operate);
        return $this->view->fetch();
    }


    /**
     * 生成查询所需要的条件,排序方式
     * @param mixed $searchfields 快速查询的字段
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
        unset($filter["table_type"]);
        unset($op["table_type"]);
        unset($filter["username"]);
        unset($op["username"]);
        unset($filter["app_name"]);
        unset($op["app_name"]);
        $filter = $filter ? $filter : [];
        $where = [];
        $tableName = '';
        if ($relationSearch) {
            $sortArr = explode(',', $sort);
            foreach ($sortArr as $index => & $item) {
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
    

}
