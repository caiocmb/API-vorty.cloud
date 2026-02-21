<?php

use Src\Controllers\AnimalController;
use Src\Staticspages\RequestResponse;
use Src\Middlewares\JwtMiddleware;

$ResResp = new RequestResponse();
$route = new AnimalController();

// valida autenticação
$middleware = new JwtMiddleware($_ENV['KEY_JWT']);

$middleware->handle($ResResp->ApiRequest(), function($request) {
    // Se chegou aqui, o token é válido
    global $ResResp,$route;

    $route->Send($ResResp,$ResResp->ApiRequest(),$request['id']);

},$ResResp);

// retorna resposta da api
$ResResp->ApiResponse();