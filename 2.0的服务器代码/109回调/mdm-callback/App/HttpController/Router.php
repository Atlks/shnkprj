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
        $routeCollector->addRoute("GET","/app/{tag}/{udid}","/index/download");
        $routeCollector->addRoute("HEAD","/app/{tag}/{udid}","/index/download");

        $routeCollector->addRoute("GET","/mdm/{tag}/{udid}","/index/download");
        $routeCollector->addRoute("HEAD","/mdm/{tag}/{udid}","/index/download");

        $this->parseParams(AbstractRouter::PARSE_PARAMS_IN_GET);
        $this->parseParams(AbstractRouter::PARSE_PARAMS_IN_POST);

        /*
          * eg path : /router/index.html  ; /router/ ;  /router
         */
//        $routeCollector->get('/router','/test');
        /*
         * eg path : /closure/index.html  ; /closure/ ;  /closure
         */
//        $routeCollector->get('/closure',function (Request $request,Response $response){
//            $response->write('this is closure router');
//            //不再进入控制器解析
//            return false;
//        });
    }
}