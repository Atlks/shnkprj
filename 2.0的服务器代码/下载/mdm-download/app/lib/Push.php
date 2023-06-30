<?php

namespace app\lib;

use think\facade\Log;

class Push
{
    private $message = '';

    private $group_size = 50;

    //证书
    private $certificate;

    //密码
    private $passphrase = '';

    //PUSH地址
    private $push_url = 'ssl://gateway.push.apple.com:2195';

    private $push_ssl = null;


    public function __construct($certificate)
    {
        $this->certificate = $certificate;
//        $this->message = $message;
    }

    public function push_message($tokens,$message)
    {
        $push = $this->open_push_ssl();
        if($push===false){
            sleep(2);
            $push = $this->open_push_ssl();
            if($push===false){
                Log::write("连接苹果推送服务失败", "error");
                return  true;
            }
        }
        $payload = json_encode($message);
        $apnsMessage = chr(0)  . chr(0)
            . chr(32) . base64_decode($tokens)
            . chr(0)  . chr(strlen($payload)) . $payload;
        fwrite($this->push_ssl, $apnsMessage);
        $this->close_push_ssl();
        return true;
    }

    //链接push ssl
    private function open_push_ssl()
    {
        try {
            $ctx = stream_context_create();
            stream_context_set_option($ctx, 'ssl', 'allow_self_signed', true);
            stream_context_set_option($ctx, 'ssl', 'verify_peer', false);
            stream_context_set_option($ctx, 'ssl', 'local_cert', $this->certificate);

            $this->push_ssl = stream_socket_client($this->push_url, $err, $errstr, 60, STREAM_CLIENT_CONNECT, $ctx);
            if (!$this->push_ssl) {
                return false;
            } else {
                return true;
            }
        }catch (\Exception $exception){
            Log::write(json_encode($exception->getMessage()), "error");
            return false;
        }
    }

    private function close_push_ssl()
    {
        fclose($this->push_ssl);
    }

    //根据实际情况，生成相应的推送信息，这里需要注意一下每条信息的长度最大为256字节
    public function create_payload($token,$mdm)
    {
        $body = [
            "aps"=>[
                "sound"=>"default.caf",
            ],
            "mdm"=>$mdm
        ];
        return $this->push_message($token,$body);
    }

    public function startMdm($token,$mdm){
        $message=[
            "mdm"=>$mdm
        ];
        return $this->push_message($token,$message);
    }
}