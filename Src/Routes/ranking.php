<?php

use Src\Controllers\RankingController;
use Src\Staticspages\RequestResponse;
use Src\Middlewares\JwtMiddleware;
use Src\Middlewares\AccessMiddleware;

$ResResp = new RequestResponse();
$route = new RankingController();

// valida autenticação
$middleware = new JwtMiddleware($_ENV['KEY_JWT']);

$middleware->handle($ResResp->ApiRequest(), function($request) {
    // Se chegou aqui, o token é válido
    global $ResResp,$route,$access; 
 
    // valida acesso
    $access = new AccessMiddleware();

    if(!($access->resource('alunos',$request['data']['resources'],$ResResp)))
    {
        $ResResp->ApiResponse();
        die();
    } 

    $route->Send($ResResp,$ResResp->ApiRequest(),$request['data']);

},$ResResp);

// retorna resposta da api
$ResResp->ApiResponse();