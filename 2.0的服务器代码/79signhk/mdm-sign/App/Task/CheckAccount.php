<?php


namespace App\Task;

use App\Lib\Oss;
use App\Lib\Tool;
use App\Mode\Enterprise;
use App\Mode\AccountAutoObtainedLog;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class CheckAccount implements TaskInterface
{

    public function run(int $taskId, int $workerIndex)
    {
        $tool = new Tool();
        $oss = new Oss();
        $ausign = ROOT_PATH . '/sign/kxsign';
        $list = DbManager::getInstance()->invoke(function ($client) {
            return Enterprise::invoke($client)
//                ->where('status', 1)
                ->field("id,name,oss_path,password")
                ->all();
        });
        $list = json_decode(json_encode($list), true);
        foreach ($list as $k => $v) {
            $soucre_path = ROOT_PATH . '/cache/' .uniqid();
            if (!is_dir($soucre_path)) {
                mkdir($soucre_path, 0777, true);
            }
            copy(ROOT_PATH . "/other/wwdrg3.pem", "$soucre_path/wwdrg3.pem");
            $save_name = $v['id'] . '.p12';
            if ($oss->ossDownload($v['oss_path'], $soucre_path . '/' . $save_name)) {
                exec("cd $soucre_path && /usr/bin/openssl pkcs12 -in $soucre_path/$save_name -out $soucre_path/ios.pem -nodes -passin pass:\"" . $v["password"] . "\"");
                exec("cd $soucre_path && /usr/bin/openssl x509 -in ios.pem -noout -ocsp_uri", $log);
                if (isset($log[0]) && strpos($log[0], "ocsp.apple.com")) {
                    $ocsp_url = $log[0];
                } else {
                    $ocsp_url = "http://ocsp.apple.com/ocsp03-wwdrg302";
                }
                $log = null;
                exec("cd $soucre_path && /usr/bin/openssl ocsp -issuer $soucre_path/wwdrg3.pem -cert $soucre_path/ios.pem -text -url $ocsp_url -noverify ", $log, $status);
                $string = implode(";", $log);
                if (strstr($string, "ios.pem: revoked")) {
                    $insert = [
                        'account_id' => $v['id'],
                        'account' => $v['name'],
                        'msg' => $log[0],
                        'create_time' => date('Y-m-d H:i:s')
                    ];
                    DbManager::getInstance()->invoke(function ($client) use ($v, $insert) {
                        Enterprise::invoke($client)->where('id', $v['id'])->update(['status' => 0]);
                        AccountAutoObtainedLog::invoke($client)->data($insert)->save();
                    });
                    $url = "https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
                    $post_data = [
                        "chat_id" => "-333087236",
                        "text" => "签名 2.0 证书已失效,账号：" . $v["name"] . " ;\r\n" . $log[0],
                    ];
                    $this->http_client($url, $post_data);
                    Logger::getInstance()->info("账号测试===" . $v['name'] . "==" . $status . "==已移除===");
                } else {
                    Logger::getInstance()->info("账号测试===" . $v['name'] . "==" . $status . "==正常===");
                }

//                if(empty($v["password"])){
//                    $shell = "cd $soucre_path && $ausign --cert $save_name -p \"\"";
//                }else{
//                    $shell = "cd $soucre_path && $ausign --cert $save_name -p ".$v["password"];
//                }
//                exec($shell,$log,$status);
//                $result = json_decode($log[0],true);
//                if(!empty($result)&&$result['status']==1){
//                    if($result['message']['CertStatus']!='GOOD'){
//                        $insert =[
//                            'account_id'=>$v['id'],
//                            'account'=>$v['name'],
//                            'msg'=>$log[0],
//                            'create_time'=>date('Y-m-d H:i:s')
//                        ];
//                        DbManager::getInstance()->invoke(function ($client)use($v,$insert){
//                            Enterprise::invoke($client)->where('id',$v['id'])->update(['status'=>0]);
//                            AccountAutoObtainedLog::invoke($client)->data($insert)->save();
//                        });
//                        $url="https://api.telegram.org/bot1570861671:AAFoeYznUNYhNGgj5yKFby36SoNAITetkmc/sendMessage";
//                        $post_data=[
//                            "chat_id"=>"-333087236",
//                            "text"=>"签名 2.0 证书已失效,账号：".$v["name"]." ;\r\n".$log[0],
//                        ];
//                        $this->http_client($url,$post_data);
//                    }
//                }
//                Logger::getInstance()->info("账号测试===".$v['name']."==".$status."==");
            } else {
                Logger::getInstance()->waring("账号测试===" . $v['name'] . "==证书下载失败==");
            }
            $log = null;
            $tool->clearFile($soucre_path);
        }
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // TODO: Implement onException() method.
    }

    function http_client($url, $data = [], $header = [])
    {
        $client = new HttpClient();
        $client->setUrl($url);
        $client->setTimeout(60);
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

}