<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::any("/index/checkIn/:token","index/checkIn")
    ->pattern(['token'=>'[\w|\-]+']);
Route::any("/index/server/:token","index/server")
    ->pattern(['token'=>'[\w|\-]+']);;
Route::get("/app/:tag/:udid","index/appReturn");
Route::any("/index/get_udid/:uuid","index/get_udid");

Route::get('/:uuid','index/index')
    ->pattern(['uuid'=>'[a-zA-Z0-9]{2,10}'])
    ->completeMatch(true)
    ->ext('html');

Route::get('/:uuid','index/index')
    ->pattern(['uuid'=>'[a-zA-Z0-9]{2,10}'])
    ->completeMatch(true);

Route::any('/index/get_customer_st',"index/get_customer_st");
Route::get('/test/make_st',"index/make_st");
Route::post('/getlink',"index/get_link");


