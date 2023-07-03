<?php


namespace App\HttpController;


use EasySwoole\Http\AbstractInterface\AbstractRouter;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use FastRoute\RouteCollector;

class Router extends AbstractRouter
{
    function initialize(RouteCollector $routeCollector)
    {
        $routeCollector->get("/app/{tag}/{udid}","/index/download");
        $routeCollector->head("/app/{tag}/{udid}","/index/download");
//        $routeCollector->head("/app/{tag}/{udid}",function (Request $request,Response $response){
//            $response->withStatus(200);
//            $response->end();
//            return false;
//        });
        /*
          * eg path : /router/index.html  ; /router/ ;  /router
//         */
            $routeCollector->get('/router','/test');
//        /*
//         * eg path : /closure/index.html  ; /closure/ ;  /closure
//         */
//        $routeCollector->get('/closure',function (Request $request,Response $response){
//            $response->write('this is closure router');
//            //不再进入控制器解析
//            return false;
//        });
    }
}