<?php

namespace app\controller;

use app\BaseController;
use app\library\GoogleOss;
use think\facade\Log;
class Index extends BaseController
{
    public function index()
    {
        echo "";
    }

    public function upload()
    {
        return json(["code" => 1]);
//        $key = $this->request->param("key",null);
//        if(empty($key)){
//            return json(["msg"=>"fail"])->code(400)->allowCache(true);
//        }
//        $key_arrays = explode("/",$key);
//        if($key_arrays[0]!="uploads"){
//            return json(["msg"=>"fail"])->code(400)->allowCache(true);
//        }
        $host = "https://" . request()->host();
        $method = request()->method();
        if ($method == "OPTIONS") {
            return json(["code" => 1]);
        }
        $result = [
            "code" => 1,
            "data" => ['url' => $host, 'fullurl' => $host],
            "msg" => "上传成功",
            "url" => $host,
            "wait" => 3,
        ];
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');
        if (empty($file)) {
            $result["code"] = 0;
            $result["msg"] = "上传失败，文件不存在";
            return json($result);
        }
        // 上传到本地服务器
        $save_path = public_path() . "apk/" . date("Y-m-d");
        $save_name = $file->md5() . "." . $file->extension();
        $file->move($save_path, $save_name);
        $file_path = $save_path . "/" . $save_name;
        if (is_file($file_path)) {
            $apk_url = $host . "/apk/" . date("Y-m-d") . "/" . $save_name;
            $result["url"] = $apk_url;
            $result["data"] = ['url' => $apk_url, 'fullurl' => $apk_url];
            return json($result);
        }
        unlink($file_path);
        $result["code"] = 0;
        $result["msg"] = "上传失败";
        return json($result)->code(200)->allowCache(true);
    }


    public function apk_async()
    {
        $key = $this->request->post("key", "");
        $sign = $this->request->post("sign", "");
        $md5_str = "uSl!I~vGjYQHJUXjTxUO";
        if (md5($key . $md5_str) != $sign) {
            return json(["code" => 1]);
        }
        $info = pathinfo($key);
        if (empty($info["dirname"]) || empty($info["basename"])) {
            return json(["code" => 1]);
        }
        $save_path = public_path() . $info["dirname"];
        $save_name = $info["basename"];
        if(!is_dir($save_path."/")){
            mkdir($save_path."/",0777,true);
        }
        $host = "https://" . request()->host();
        $method = request()->method();
        if ($method == "OPTIONS") {
            return json(["code" => 1]);
        }
        $result = [
            "code" => 1,
            "msg" => "上传成功",
            "url" => $host,
            "wait" => 3,
            "key" =>  $save_name,
        ];
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');
        if (empty($file)) {
            $result["code"] = 0;
            $result["msg"] = "上传失败，文件不存在";
            return json($result);
        }
        // 上传到本地服务器
        $file->move($save_path, $save_name);
        $file_path = $save_path . "/" . $save_name;
        if (is_file($file_path)) {
            return json($result);
        }
        $result["code"] = 0;
        $result["msg"] = "上传失败";
        return json($result)->code(200)->allowCache(true);
    }

    public function apk_del(){
        $key = $this->request->post("key", "");
        $sign = $this->request->post("sign", "");
        $md5_str = "8^6kJ6MYz_7SwPLZlxeC";
        if (md5($key . $md5_str) != $sign) {
            return json(["code" => 1]);
        }
        $file = public_path().$key;
        if(is_file($file)){
            @unlink($file);
        }
        return json(["code" => 1]);
    }

    public function f_auth(){
        $params = $this->request->param();
//        if(empty($params["p1"])||empty($params["p2"])||empty($params["Expires"])||empty($params["Signature"])){
//            header('HTTP/1.1 404');
//            exit();
//        }
//        $key = "yub7STybT6gtDF3wiDjDvaPX7cIeGp";
//        $sign = $params["Signature"];
//        $new_sign = sha1($key.'apk/'.$params["p1"]."/".$params["p2"].$params["Signature"]);
//        if($sign==$new_sign){
            if(is_file(public_path() ."apk/".$params["p1"]."/".$params["p2"])) {
                header("Content-Type:application/octet-stream;");
                header("Content-Disposition:attachment;filename=" . $params["p2"]);
                header("X-Accel-Redirect: /oss/apk/" . $params["p1"] . "/" . $params["p2"]);
            }else{

                header('HTTP/1.1 404');
                exit();
            }
//        }else{
//            header('HTTP/1.1 404');
//            exit();
//        }
    }

}
