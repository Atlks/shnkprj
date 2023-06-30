<?php


namespace App\HttpController;


use EasySwoole\Http\AbstractInterface\AbstractRouter;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use FastRoute\RouteCollector;

class Router extends AbstractRouter
{
    public function initialize(RouteCollector $routeCollector)
    {
        // TODO: Implement initialize() method.
//        $routeCollector->head('/proxy/{tag}/{udid}',function (Request $request,Response $response){
//            $response->withStatus(200);
//            $response->end();
//            return false;
//        });
//        $routeCollector->head('/callback/{tag}/{udid}',function (Request $request,Response $response){
//            $response->withStatus(200);
//            $response->end();
//            return false;
//        });
//        $routeCollector->head('/flow/ipa/{tag}',function (Request $request,Response $response){
//            $response->withStatus(200);
//            $response->end();
//            return false;
//        });
        $routeCollector->addRoute(['GET','POST','HEAD'],'/callback/{tag}/{udid}','/Index/download');
        $routeCollector->addRoute(['GET','POST','HEAD'],'/proxy/{tag}/{udid}','/Proxy/download');
        /**分发下载***/
        $routeCollector->addRoute(['GET','POST','HEAD'],'/flow/ipa/{tag}','/Index/get_ipa');
    }

}