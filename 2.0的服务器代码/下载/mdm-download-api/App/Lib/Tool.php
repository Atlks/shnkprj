<?php


namespace App\Lib;


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

}