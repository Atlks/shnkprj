<?php


namespace App\HttpController;


use App\Task\proxy\DistributePrivateTask;
use App\Task\proxy\DistributeTask;
use App\Task\proxy\ProxyInvalid;
use App\Task\proxy\ProxyPackageUpdate;
use App\Task\proxy\ProxySign;
use App\Task\proxy\ProxyVersion;
use App\Utility\Ip2Region;
use App\Utility\Redis;
use App\Utility\Tool;
use co;
use EasySwoole\EasySwoole\Swoole\Task\TaskManager;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\MysqliPool\Connection;
use EasySwoole\MysqliPool\Mysql;
use Throwable;

class Proxy extends Controller
{
    public function index()
    {
        // TODO: Implement index() method.
        $this->response()->write('hello this is proxy success');
    }

    /**
     * 下载
     * @throws Throwable
     */
    public function download()
    {
        $tag = $this->request()->getRequestParam('tag');
        $udid = $this->request()->getRequestParam('udid');
        if(empty($tag)||empty($udid)){
            return $this->writeJson(200);
        }
        $for = 0;
        $is_exit = null;
        while ($for < 600) {
            $redis = Redis::init();
            $is_exit = $redis->get($udid);
            $redis->close();
            if ($is_exit) {
                break;
            } else {
                $for += 2;
                co::sleep(2);
                continue;
            }
        }
        if($is_exit){
            $is = json_decode($is_exit,true);
            if($is['code']==1){
                $tool = new Tool();
                $app = Mysql::invoker("mysqlProxy",function (Connection $db)use ($tag){
                    return $db->where('tag', $tag)
                        ->getOne('proxy_app');
                });
                $user_id = $app["user_id"];
                $user = Mysql::invoker("mysqlProxy",function (Connection $db)use ($user_id){
                    return $db->where('id', $user_id)
                        ->getOne('proxy_user');
                });
                /**模式2**/
                if($app["mode"]==2){
                    $url = $tool->proxySignOssUrl($is["data"]['oss_path'], $is["data"]["is_overseas"]);
                    $this->response()->redirect($url, 301);
                    $this->response()->end();
                }else {
                    $table = $tool->getTable("proxy_download", $user["pid"]);
                    $download = Mysql::invoker('mysqlProxy', function (Connection $db) use ($udid, $tag, $table) {
                        return $db->where('udid', $udid)
                            ->where('tag', $tag)
                            ->getOne($table);
                    });
                    if ($download["update_time"]) {
                        if (strtotime($download["update_time"]) >= (time() - 600) && $download["num"] <= 5) {
                            /***指定下载域名***/
                            if (strrpos($download['download'], "https") !== false) {
                                $url = $download["download"];
                            }else{
                                $url = $tool->proxySignOssUrl($download['download'], $download["is_overseas"]);
                            }
                            Mysql::invoker("mysqlProxy", function (Connection $db) use ($download, $table) {
                                $db->where("id", $download["id"])->update($table, ['num' => $db->inc(1)]);
                            });
                            $this->response()->redirect($url, 301);
                            $this->response()->end();
                        } else {
                            $this->writeJson(200);
                        }
                    } else {
                        if (strtotime($download["create_time"]) >= (time() - 600) && $download["num"] <= 5) {
                            /***指定下载域名***/
                            if (strrpos($download['download'], "https") !== false) {
                                $url = $download["download"];
                            }else{
                                $url = $tool->proxySignOssUrl($download['download'], $download["is_overseas"]);
                            }
                            Mysql::invoker("mysqlProxy", function (Connection $db) use ($download, $table) {
                                $db->where("id", $download["id"])->update($table, ['num' => $db->inc(1)]);
                            });
                            $this->response()->redirect($url, 301);
                            $this->response()->end();
                        } else {
                            $this->writeJson(200);
                        }
                    }
                }
            }else{
                $this->writeJson(200);
            }
        }else{
            $this->writeJson(200);
        }
    }


    public function get_ipa(){
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
        $info =  Mysql::invoker("mysqlProxy", function (Connection $db) use ($tag) {
            return $db->where('tag', $tag)
                ->getOne('flow_app');
        });
        if (empty($info)||$info["is_st"]==1) {
            return  $this->writeJson(200, 'success');
        }
        $flow_info =  Mysql::invoker("mysqlProxy", function (Connection $db) use ($info) {
            return $db->where('user_id', $info["user_id"])
                ->getOne('user_flow');
        });
        $flow = $flow_info["flow"]??0;
        if ($flow < $info["filesize"] || empty($info["filesize"])) {
            return  $this->writeJson(404, 'fail');
        }
        $data = [
            "app_id" => $info["id"],
            "user_id" => $info["user_id"],
            "package_type" => "IPA",
            "flow" => $info["filesize"],
            "ip" => $ip,
            "create_time" => date("Y-m-d H:i:s"),
        ];
        $last_flow = bcsub($flow, $info["filesize"]);
        Mysql::invoker("mysqlProxy", function (Connection $db) use ($data) {
            $db->insert("flow_app_download_log",$data);
        });
        Mysql::invoker("mysqlProxy", function (Connection $db) use ($last_flow,$info) {
            $db->where("user_id", $info["user_id"])->update("user_flow", ["flow" => $last_flow]);
        });
        $ip2 = new Ip2Region();
        $ip_address = $ip2->binarySearch($ip);
        $address = explode('|', $ip_address['region']);
        if ($address[0] == "中国" && !in_array($address[2], ["澳门", '香港', "台湾省"])) {
            $is_overseas = 10;
        } else {
            $is_overseas= 20;
        }
        $tool = new Tool();
        $url = $tool->proxySignOssUrl($info["oss_path"], $is_overseas);
        $this->response()->redirect($url,301);
        $this->response()->end();
    }



}