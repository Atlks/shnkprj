<?php


namespace app\admin\controller;

use app\common\controller\Backend;
use think\Db;

/**
 * APP对比
 * @package app\admin\controller
 */
class AppScale extends Backend
{
    protected $model = null;

    protected $searchFields = "";

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\AppInstallCallback;

    }

    public function index()
    {
        if ($this->request->isAjax()) {
            $list = $this->model->whereTime("create_time", "-30 days")
                ->cache(true,21600)
                ->group("app_id")
                ->column("app_id,user_id,count(*) as install_num");
            $app_id = array_column($list, "app_id");
            $fee_list = [];
            for ($i = 0; $i < 10; $i++) {
                $table = "proxy_bale_rate_" . $i;
                $cache_list = Db::table($table)
                    ->alias("b")
                    ->join("proxy_app", "b.app_id=proxy_app.id", "LEFT")
                    ->where("b.status", 1)
                    ->where("b.is_auto", 0)
                    ->whereIn("b.app_id", $app_id)
                    ->whereTime("b.create_time", "-30 days")
                    ->cache(true,21600)
                    ->group("b.app_id")
                    ->column("b.app_id,b.user_id,count(*) as fee_num,proxy_app.name");
                $fee_list = array_merge($fee_list, $cache_list);
            }
            foreach ($list as $k => $v) {
                foreach ($fee_list as $key => $val) {
                    if ($v["app_id"] == $val["app_id"]) {
                        $list[$k]["name"] = $val["name"];
                        $list[$k]["fee_num"] = $val["fee_num"];
                        $diff = $val["fee_num"] - $v["install_num"];
                        $list[$k]["diff"] = $diff;
                        if ($diff >= 0) {
                            unset($list[$k]);
                        }
                        break;
                    }
                }
            }
            foreach ($list as $k => $v) {
                if (!isset($list[$k]["diff"])) {
                    $list[$k]["name"] = "";
                    $list[$k]["fee_num"] = 0;
                    $list[$k]["diff"] = 0;
                }
            }
            array_multisort(array_column($list, "diff"), SORT_ASC, $list);
            $result = array("total" => count($list), "rows" => array_values($list));
            return json($result);
        }
        return $this->view->fetch();
    }

}