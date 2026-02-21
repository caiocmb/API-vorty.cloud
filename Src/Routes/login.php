<?php

use Src\Controllers\LoginController;
use Src\Staticspages\RequestResponse;

$ResResp = new RequestResponse();
$login = new LoginController();

//valida os inputs, faz login e retorna o token
$login->FazLogin($ResResp,$ResResp->ApiRequest());

$ResResp->ApiResponse();