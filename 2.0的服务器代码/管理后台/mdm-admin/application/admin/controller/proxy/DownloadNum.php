<?php


namespace app\admin\controller\proxy;



use app\admin\model\ProxyApp;
use app\common\controller\Backend;
use app\common\library\Redis;
use think\Db;

/**
 * 每日下载排行管理
 *
 * @icon fa fa-download
 */
class DownloadNum extends Backend
{
    /**
     * Download模型对象
     * @var \app\admin\model\proxy\BaleRate
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\proxy\BaleRate();

    }

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = false;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax())
        {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField'))
            {
                return $this->selectpage();
            }
            $search = $this->request->get("search",null);
            $time = json_decode($this->request->get('filter'),true);
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            if(isset($time["create_time"])&& !empty($time["create_time"])){
                $time_key = str_replace(' - ', ',', $time["create_time"]);
                $time_keys = array_slice(explode(',', $time_key), 0, 2);
                $time_key = ":".date("Ymd",strtotime($time_keys[0]))."_".date("Ymd",strtotime($time_keys[1]));
            }else{
                $time_key ="";
                $time_keys =[];
            }
            $redis = new Redis([ 'select' => 12]);
            $total = $redis->handle()->zCard("app_today_download".$time_key);
            $redis->handle()->close();
            if($total<=0){
                $this->read_cache($time_key,$time_keys);
                $redis = new Redis([ 'select' => 12]);
                $total = $redis->handle()->zCard("app_today_download".$time_key);
                $redis->handle()->close();
            }
            if(isset($time["app_id"])&&!empty($time["app_id"])){
                $app_ids = ProxyApp::where("is_delete",1)
                    ->where("id",$time["app_id"])
                    ->column("id,name,user_id");
            }
            if(!empty($search)){
                $app_ids = ProxyApp::where("is_delete",1)
                    ->where("pay_num",'>=',1)
                    ->whereLike("name","%$search%")
                    ->column("id,name,user_id");
            }
            $get_list = [];
            if(!empty($app_ids)){
                foreach ($app_ids as $v){
                    $redis = new Redis([ 'select' => 12]);
                    $num = $redis->handle()->zScore("app_today_download".$time_key,$v["id"].":".$v["name"]);
                    $redis->handle()->close();
                    $get_list[$v["id"].":".$v["name"]]= $num?$num:0;
                }
                $total  = count($get_list);
            }else {
                $redis = new Redis([ 'select' => 12]);
                if ($order == "asc") {
                    $get_list = $redis->handle()->zRange("app_today_download".$time_key, $offset, ($offset + $limit-1),true);
                } else {
                    $get_list = $redis->handle()->zRevRange("app_today_download".$time_key, $offset, ($offset + $limit-1),true);
                }
            }
            $result = [];
            foreach ($get_list as $k=>$v){
                $cache = explode(":",$k);
                if(!isset($cache[1])){
                    continue;
                }
                $result[] =[
                    "app_id"=>$cache[0],
                    "num"=>$v,
                    "name"=> $cache[1],
                    "create_time"=>empty($time_key)?date("Y-m-d"):implode("-",$time_keys)
                ];
            }
            $result = array("total" => $total, "rows" => $result,"extend"=>["offset"=>$offset,'limit'=>$limit,"order"=>$order]);
            return json($result);
        }
        return $this->view->fetch();
    }

    protected function read_cache($time_key="",$time_keys=[]){
        if(!empty($time_key)){
            for ($i=0;$i<10;$i++){
                $table = "proxy_bale_rate_".$i;
                $cache_list = Db::table($table)
                    ->where("status",1)
                    ->whereTime("create_time",$time_keys)
                    ->group('app_id')
                    ->column("app_id,user_id,count(id) as num");
                $redis = new Redis([ 'select' => 12]);
                $app = new ProxyApp();
                foreach ($cache_list as $v){
                    $app_name = $app->where('id',$v["app_id"])->value('name');
                    $redis->handle()->zAdd("app_today_download".$time_key,(int)$v["num"],$v["app_id"].":".$app_name);
                }
                $redis->handle()->close();
            }
            $redis = new Redis([ 'select' => 12]);
            $redis->expire("app_today_download".$time_key,30*60);
            $redis->handle()->close();
        }else{
            for ($i=0;$i<10;$i++){
                $table = "proxy_bale_rate_".$i;
                $cache_list = Db::table($table)
                    ->where("status",1)
                    ->whereTime("create_time","d")
                    ->group('app_id')
                    ->column("app_id,user_id,count(id) as num");
                $redis = new Redis([ 'select' => 12]);
                $app = new ProxyApp();
                foreach ($cache_list as $v){
                    $app_name = $app->where('id',$v["app_id"])->value('name');
                    $redis->handle()->zAdd("app_today_download",(int)$v["num"],$v["app_id"].":".$app_name);
                }
                $redis->handle()->close();
            }
            $redis = new Redis([ 'select' => 12]);
            $redis->expire("app_today_download",30*60);
            $redis->handle()->close();
        }

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