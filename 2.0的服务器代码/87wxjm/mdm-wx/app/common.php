<?php
// 应用公共文件
if(!function_exists("getTable")){
    function getTable($table , $user_id ,$sn=10)
    {
        /**获取用户ID对应的表序列***/
        $ext = intval(fmod(floatval($user_id), $sn));
        return $table . "_" . $ext;
    }
}
if (!function_exists('format_bytes')) {

    /**
     * 将字节转换为可读文本
     * @param int    $size      大小
     * @param string $delimiter 分隔符
     * @return string
     */
    function format_bytes($size, $delimiter = '')
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        for ($i = 0; $size >= 1024 && $i < 6; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . $delimiter . $units[$i];
    }
}

if (!function_exists("__")) {
    function __($name, $lang = "zh")
    {
        $lang_package = include root_path() . "/app/lang/download.php";
        if (isset($lang_package[$lang][$name]) && !empty($lang_package[$lang][$name])) {
            return $lang_package[$lang][$name];
        } else {
            return $name;
        }
    }
}

/**
 * 短链接特殊符号
 */
if(!function_exists("get_short_url")){
    function get_short_url($str=""){
        $array = str_split($str);
        $result = "";
        foreach ($array as $v){
            if(ctype_alnum($v)){
                $result.=$v;
            }
        }
        return $result;
    }
}