<?php
namespace Src\Middlewares;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtMiddleware
{
    private $secret;

    public function __construct($secretKey)
    {
        $this->secret = $secretKey;
    }

    public function handle($request, $next, $response) 
    {   
        $headers = (object)$request;

        if (!isset($headers->header['Authorization'])) {
            $response->status = 'error';
            $response->code_error = 401;
            $response->message = 'Header Authorization não encontrado';

            return false;
            die();
        }

        $authHeader = $headers->header['Authorization'];
        $token = str_replace('Bearer ', '', $authHeader);

        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            $request['id'] = (array) $decoded;
            return $next($request);
        } catch (\Exception $e) {
            $response->status = 'error';
            $response->code_error = 401;
            $response->message = 'Token invalido! '.$e->getMessage();

            return false;
            die();
        }
    }
}
