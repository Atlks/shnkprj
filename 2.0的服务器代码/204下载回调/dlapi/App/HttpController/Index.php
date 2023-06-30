<?php

namespace App\HttpController;

use App\Utility\Ip2Region;
use App\Utility\Redis;
use App\Utility\Tool;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\MysqliPool\Connection;
use EasySwoole\MysqliPool\Mysql;

class Index extends Controller
{

    public function index()
    {
        // TODO: Implement index() method.
        $this->response()->write('hello world');

    }

    /**
     * 下载
     * @throws \Throwable
     */
    public function download()
    {
        $tag = $this->request()->getRequestParam('tag');
        $udid = $this->request()->getRequestParam('udid');
        if (empty($tag) || empty($udid)) {
            return $this->writeJson(400, 'fail');
        }
        $redis = Redis::init();
        $for = 0;
        $is_exit = null;
        while ($for < 600) {
            $is_exit = $redis->get($udid);
            if ($is_exit) {
                break;
            } else {
                $for += 2;
                \co::sleep(2);
                continue;
            }
        }
        $redis->close();
        if ($is_exit) {
            $is = json_decode($is_exit, true);
            if ($is['code'] == 1) {
                $app = Mysql::invoker("mysql", function (Connection $db) use ($tag) {
                    return $db->where('tag', $tag)
                        ->getOne('app');
                });
                $tool = new Tool();
                $download_table = $tool->getTable("download", $app["user_id"]);
                $download = Mysql::invoker("mysql", function (Connection $db) use ($udid, $tag, $download_table) {
                    return $db->where('udid', $udid)
                        ->where('tag', $tag)
                        ->getOne($download_table);
                });
                if ($download["update_time"]) {
                    if (strtotime($download["update_time"]) >= (time() - 600) && $download["num"] < 5) {
                        $url = $tool->signOssUrl($download['download'], $download["is_overseas"]);
                        Mysql::invoker("mysql", function (Connection $db) use ($download, $download_table) {
                            $db->where("id", $download["id"])->update($download_table, ['num' => $db->inc(1)]);
                        });
                        $this->response()->redirect($url,301);
                        $this->response()->end();
                    } else {
                        $this->writeJson(400, 'fail');
                    }
                } else {
                    if (strtotime($download["create_time"]) >= (time() - 600) && $download["num"] < 5) {
                        $url = $tool->signOssUrl($download['download'], $download["is_overseas"]);
                        Mysql::invoker("mysql", function (Connection $db) use ($download, $download_table) {
                            $db->where("id", $download["id"])->update($download_table, ['num' => $db->inc(1)]);
                        });
                        $this->response()->redirect($url,301);
                        $this->response()->end();
                    } else {
                        $this->writeJson(400, 'fail');
                    }
                }
            } else {
                $this->writeJson(400, 'fail');
            }
        } else {
            $this->writeJson(400, 'fail');
        }
    }


    public function get_ipa(){
        $type = $this->request()->getMethod();
        $tag = $this->request()->getRequestParam('tag');
        $heard = $this->request()->getHeaders();
        if(isset($heard["x-forwarded-for"])&&!empty($heard["x-forwarded-for"])){
            $arr = $heard["x-forwarded-for"];
            $pos = array_search('unknown', $arr);
            if (false !== $pos) {
                unset($arr[$pos]);
            }
            $ip = trim(current($arr));
        }elseif (isset($heard["client-ip"])&&!empty($heard["client-ip"])){
            $arr = $heard["client-ip"];
            $pos = array_search('unknown', $arr);
            if (false !== $pos) {
                unset($arr[$pos]);
            }
            $ip = trim(current($arr));
        }else{
            $ip = "127.0.0.1";
        }
        $info =  Mysql::invoker("mysql", function (Connection $db) use ($tag) {
            return $db->where('tag', $tag)
                ->getOne('flow_app');
        });
        $flow_info =  Mysql::invoker("mysql", function (Connection $db) use ($info) {
            return $db->where('user_id', $info["user_id"])
                ->getOne('user_flow');
        });
        $flow = $flow_info["flow"]??0;
        $use_size = $info["filesize"]*2;
        if ($flow < $use_size || empty($use_size)) {
            return  $this->writeJson(404, 'fail');
        }
        if($type=="GET") {
            $data = [
                "app_id" => $info["id"],
                "user_id" => $info["user_id"],
                "package_type" => "IPA",
                "flow" => $use_size,
                "ip" => $ip,
                "create_time" => date("Y-m-d H:i:s"),
            ];
            $last_flow = bcsub($flow, $use_size);
            Mysql::invoker("mysql", function (Connection $db) use ($data) {
                $db->insert("flow_app_download_log", $data);
            });
            Mysql::invoker("mysql", function (Connection $db) use ($last_flow, $info) {
                $db->where("user_id", $info["user_id"])->update("user_flow", ["flow" => $last_flow]);
            });
        }
        if (empty($info)||$info["is_st"]==1) {
            return  $this->writeJson(200, 'success');
        }
        $ip2 = new Ip2Region();
        $ip_address = $ip2->binarySearch($ip);
        $address = explode('|', $ip_address['region']);
        if ($address[0] == "中国" && !in_array($address[2], ["澳门", '香港', "台湾省"])) {
           $is_overseas = 10;
        } else {
            $is_overseas= 20;
        }
        $tool = new Tool();
        $url = $tool->signOssUrl($info["oss_path"], $is_overseas);
        $this->response()->redirect($url,301);
        $this->response()->end();
    }


}