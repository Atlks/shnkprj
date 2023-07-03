<?php
namespace app\controller;

use app\BaseController;
use app\library\GoogleOss;

class Index extends BaseController
{
    public function index()
    {
       echo "";
    }

   public function upload(){
        $key = $this->request->param("key",null);
        if(empty($key)){
            return json(["msg"=>"fail"])->code(400)->allowCache(true);
        }
        $key_arrays = explode("/",$key);
        if($key_arrays[0]!="cache-uploads"){
            return json(["msg"=>"fail"])->code(400)->allowCache(true);
        }
       // 获取表单上传文件 例如上传了001.jpg
       $file = request()->file('file');
       // 上传到本地服务器
       $save_path = runtime_path()."storage/app/".date("Y-m-d");
       $save_name = $file->md5().".".$file->extension();
       $file->move( $save_path,$save_name);
//       $savename = \think\facade\Filesystem::putFile( 'cache', $file);
       $file_path = $save_path."/".$save_name;
       if(is_file($file_path)){
           $google_oss = new GoogleOss();
           if($google_oss->ossUpload($file_path,$key)){
               unlink($file_path);
               return json(["path"=>$key]);
           }
       }
       unlink($file_path);
       return json(["msg"=>"upload fail"])->code(400)->allowCache(true);
   }

}
