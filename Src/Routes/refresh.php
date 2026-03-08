<?php

use Src\Controllers\RefreshController;
use Src\Staticspages\RequestResponse;

$ResResp = new RequestResponse();
$refresh = new RefreshController();

//valida os inputs, faz login e retorna o token
$refresh->RefreshToken($ResResp,$ResResp->ApiRequest());

$ResResp->ApiResponse();