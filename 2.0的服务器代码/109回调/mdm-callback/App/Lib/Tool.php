<?php


namespace App\Lib;


use App\Mode\App;
use App\Mode\AutoAppRefush;
use App\Mode\BaleRate;
use App\Mode\SiteConfig;
use App\Mode\UdidToken;
use App\Mode\User;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\Db\ClientInterface;
use EasySwoole\ORM\DbManager;
use EasySwoole\RedisPool\RedisPool;

class Tool
{

    /**
     * 删除指定后缀文件
     * @param string $path
     * @param $file_type
     */
    public  function clearFile($path = '', $file_type = '')
    {
        if (is_dir($path) && !empty($path) && strlen($path) > 5) {
            exec("rm -rf $path");
        }
        return true;
    }

    /**
     * 去除数组空空数组
     * @param $arr
     * @return array
     */
    public function array_no_empty($arr)
    {
        if (is_array($arr)) {
            foreach ($arr as $k => $v) {
                if (is_array($v) && empty($v)) {
                    unset($arr[$k]);
                } elseif (is_array($v)) {
                    $arr[$k] = $this->array_no_empty($v);
                }
            }
        }
        return $arr;
    }

    /**
     * @param $url
     * @param array $data
     * @param array $header
     * @return \EasySwoole\HttpClient\Bean\Response
     * @throws \EasySwoole\HttpClient\Exception\InvalidUrl
     */
    function http_client($url, $data = [], $header = [])
    {
        $client = new HttpClient();
        $client->setUrl($url);
        $client->setTimeout(-1);
        if (!empty($header)) {
            $client->setHeaders($header);
        }
        if (!empty($data)) {
//            $client->setContentTypeFormData();
            $result = $client->post($data);
        } else {
            $result = $client->get();
        }
        return $result;
    }

    /**
     * 删除空格
     * @param string $str
     * @return string
     */
    public function strspacedel($str=""){
        $length = mb_strlen($str, 'utf-8');
        $array = [];
        for ($i=0;$i<$length;$i++){
            $cache_str = trim(mb_substr($str, $i, 1, 'utf-8'));
            if($cache_str){
                $array[]=$cache_str;
            }
        }
        return implode($array);
    }

    public function is_pay($app,$udid,$ip){
        try {
            $app_id = $app["id"];
            $user = DbManager::getInstance()->invoke(function ($client) use ($app) {
                $data = User::invoke($client)->where('id', $app["user_id"])
                    ->where("status", "normal")
                    ->get();
                if (!empty($data)) {
                    return $data->toArray();
                } else {
                    return null;
                }
            });
            if (empty($user) || $user["sign_num"] <= 0) {
                return false;
            }
            $user_id = $user["id"];
            $bale_rate_table = $this->getTable("proxy_bale_rate", $user["pid"]);
//            $is_second_pay = DbManager::getInstance()->invoke(function ($client) {
//                $data = SiteConfig::invoke($client)->where("name", "app_no_second_pay")
//                    ->get();
//                if (!empty($data)) {
//                    return $data->toArray();
//                } else {
//                    return null;
//                }
//            });
            /***白名单用户不在多次扣费**/
            if($user["is_second_pay"]==0){
                $is_bale_rate = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table, $app_id, $user, $udid) {
                    $data = BaleRate::invoke($client)->tableName($bale_rate_table)
                        ->where("app_id", $app_id)
                        ->where("user_id", $user["id"])
                        ->where("udid", $udid)
                        ->where("status", 1)
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
            }else{
                $create_time = date("Y-m-d ",strtotime("-2 months"));
                $is_bale_rate = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table, $app_id, $user, $udid,$create_time) {
                    $data = BaleRate::invoke($client)->tableName($bale_rate_table)
                        ->where("app_id", $app_id)
                        ->where("user_id", $user["id"])
                        ->where("udid", $udid)
                        ->where("status", 1)
                        ->where("create_time",$create_time,">")
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
            }

            $udid_token = RedisLib::hGetAll("udidToken:" . $udid, 2);
            if (empty($udid_token)) {
                $udid_token = DbManager::getInstance()->invoke(function ($client) use ($udid) {
                    $data = UdidToken::invoke($client)->where("udid", $udid)->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
                RedisLib::hMSet("udidToken:" . $udid, $udid_token, 2);
            }
            if (empty($udid_token)) {
                return false;
            }
            $ip2 = new Ip2Region();
            $ip_address = $ip2->memorySearch($ip);
            if (!empty($ip_address)) {
                $address = explode('|', $ip_address['region']);
                if ($address[0] == "中国" && !in_array($address[2], ["澳门", '香港', "台湾省", "台湾"])) {
                    $is_overseas = 10;
                } else {
                    $is_overseas = 20;
                }
            } else {
                $is_overseas = 20;
                Logger::getInstance()->info("IP 未知： $ip");
            }
            if (!empty($is_bale_rate)) {
                $update = [
                    "update_time" => date("Y-m-d H:i:s"),
                    "is_overseas" => $is_overseas,
                    'account_id' => $app['account_id'],
                    'osversion' => $udid_token["osversion"]?$udid_token["osversion"]:'',
                    'product_name' => $udid_token["product_name"]??'iPhone',
                ];
                $bale_rate_id = $is_bale_rate["id"];
                DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table, $bale_rate_id, $update) {
                    BaleRate::invoke($client)->tableName($bale_rate_table)
                        ->where("id", $bale_rate_id)
                        ->update($update);
                });
                DbManager::getInstance()->invoke(function ($client) use ($app_id) {
                    App::invoke($client)->where("id", $app_id)->update([
                        "download_num" => QueryBuilder::inc(1)
                    ]);
                });
            } else {
                $bale_rate = [
                    'app_id' => $app['id'],
                    'udid' => $udid,
                    'resign_udid' => $udid,
                    'user_id' => $user['id'],
                    'rate' => $user['rate'],
                    'pid' => $user['pid'],
                    'status' => 1,
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s'),
                    'account_id' => $app['account_id'],
                    'ip' => $ip,
                    'device' => $udid_token["name"],
                    'osversion' => $udid_token["osversion"]?$udid_token["osversion"]:'',
                    'product_name' => $udid_token["product_name"],
                    'sign_num' => 1,
                    'is_overseas' => $is_overseas,
                ];

                $rate = DbManager::getInstance()->invoke(function ($client) use ($bale_rate_table, $user_id, $bale_rate) {
                    User::invoke($client)->where("id", $user_id)->update([
                        "sign_num" => QueryBuilder::dec(1)
                    ]);
                    return BaleRate::invoke($client)->tableName($bale_rate_table)->data($bale_rate, false)->save();
                });
                /**扣费失败***/
                if (!$rate) {
                    Logger::getInstance()->error("扣费失败： " . $rate);
                    return false;
                }
                DbManager::getInstance()->invoke(function ($client) use ($app_id) {
                    App::invoke($client)->where("id", $app_id)->update([
                        "download_num" => QueryBuilder::inc(1),
                        "pay_num" => QueryBuilder::inc(1),
                    ]);
                });
                /**自动刷**/
                $is_auto = DbManager::getInstance()->invoke(function ($client) use ($app_id) {
                    $data = AutoAppRefush::invoke($client)
                        ->where("app_id", $app_id)
                        ->where("status", 1)
                        ->get();
                    if (!empty($data)) {
                        return $data->toArray();
                    } else {
                        return null;
                    }
                });
                if ($is_auto) {
                    RedisPool::invoke(function (\EasySwoole\Redis\Redis $redis) use ($app_id) {
                        $redis->select(8);
                        $redis->rPush("auto_add", $app_id);
                    });
                }
            }
            return [
                "is_overseas" => $is_overseas,
                "is_pay" => true
            ];
        }catch (\Throwable $exception){
            Logger::getInstance()->error("扣费错误： " . $exception->getMessage());
            return false;
        }
    }


    public function getTable($table, $user_id, $sn = 10)
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), $sn));
        return $table . "_" . $ext;
    }

    /***
     * 获取各国IP
     * @param string $lang
     * @return string
     */
    public function getIp($lang=""){
        /***越南IP**/
        if($lang=="vi"){
            $ip_list=[113,171,14];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="113"){
                return '113.'.rand(160,191).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="171"){
                return '171.'.rand(224,255).".".rand(0,255).".".rand(0,255);
            }else{
                return '14.'.rand(160,191).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="id"){
            /***印度尼西亚**/
            $ip_list=[39,36,120];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="36"){
                return '36.'.rand(64,95).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="120"){
                return '120.'.rand(160,191).".".rand(0,255).".".rand(0,255);
            }else{
                return '39.'.rand(192,255).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="th"){
            /***泰语**/
            $ip_list=[171,58,118];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="171"){
                return '171.'.rand(96,103).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="58"){
                return '58.'.rand(8,11).".".rand(0,255).".".rand(0,255);
            }else{
                return '39.'.rand(172,175).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="ko"){
            /***韩语**/
            $ip_list=[211,14,121];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="211"){
                return $ip_one.'.'.rand(168,255).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="14"){
                return $ip_one.'.'.rand(32,95).".".rand(0,255).".".rand(0,255);
            }else{
                return $ip_one.'.'.rand(128,191).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="ja"){
            /***日本**/
            $ip_list=[125,126,133];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="125"){
                return $ip_one.".255.".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="126"){
                return $ip_one.'.'.rand(0,255).".".rand(0,255).".".rand(0,255);
            }else{
                return $ip_one.'.'.rand(0,255).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="hi"){
            /***印度**/
            $ip_list=[117,106,122];
            $ip_one = $ip_list[array_rand($ip_list)];
            if($ip_one=="117"){
                return $ip_one.".".rand(192,255).".".rand(0,255).".".rand(0,255);
            }elseif ($ip_one=="106"){
                return $ip_one.'.'.rand(192,233).".".rand(0,255).".".rand(0,255);
            }else{
                return $ip_one.'.'.rand(106,187).".".rand(0,255).".".rand(0,255);
            }
        }elseif ($lang=="zh"){
            /***印度**/
            $ip_list=[14,27,36,42,43,45,49,58,59,60,101,103,110,111,113,114,115,116,117,118,119,120,121,123,124,139,144,140,150,153,157,160,163,167,171,175,180,182,183,185,202,203,222];
            $ip_one = $ip_list[array_rand($ip_list)];
            return $ip_one.'.'.rand(0,255).".".rand(0,255).".".rand(0,255);
        }else{
            $lang = array_rand(["vi"=>1,"id"=>2,"th"=>3,"ko"=>4,"ja"=>6,"hi"=>5]);
            return $this->getIp($lang);
        }
    }


}