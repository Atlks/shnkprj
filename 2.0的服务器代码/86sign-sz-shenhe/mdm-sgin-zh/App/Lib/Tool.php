<?php


namespace App\Lib;


use EasySwoole\HttpClient\HttpClient;

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

}