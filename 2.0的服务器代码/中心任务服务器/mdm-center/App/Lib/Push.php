<?php

namespace App\Lib;

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
    }


    public function push_message($tokens,$message)
    {
        $push = $this->open_push_ssl();
        $payload = json_encode($message);
        $apnsMessage = chr(0)  . chr(0)
            . chr(32) . base64_decode($tokens)
            . chr(0)  . chr(strlen($payload)) . $payload;
        fwrite($this->push_ssl, $apnsMessage);
        $this->close_push_ssl();
        return true;
    }

    /**批量推送
     * @param $tokens {push_magic,udid_token}
     * @return bool
     */
    public function push_list_message($tokens){
        $this->open_push_ssl();
        foreach ($tokens as $v){
            $payload = json_encode([ "mdm"=>$v["push_magic"]]);
            $apnsMessage = chr(0)  . chr(0)
                . chr(32) . base64_decode($v["udid_token"])
                . chr(0)  . chr(strlen($payload)) . $payload;
            fwrite($this->push_ssl, $apnsMessage);
        }
        $this->close_push_ssl();
        return true;
    }


    //链接push ssl
    private function open_push_ssl()
    {
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
    }

    private function close_push_ssl()
    {
        fclose($this->push_ssl);
    }

    //根据实际情况，生成相应的推送信息，这里需要注意一下每条信息的长度最大为256字节
    private function create_payload($mdm)
    {
        $body = [
            "aps"=>[
                "sound"=>"default.caf",
            ],
            "mdm"=>$mdm
        ];
        return json_encode($body);
    }

    public function startMdm($token,$mdm){
        $message=[
            "mdm"=>$mdm
        ];
        return $this->push_message($token,$message);
    }


}