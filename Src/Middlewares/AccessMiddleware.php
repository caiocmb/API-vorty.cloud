<?php 

namespace Src\Middlewares;

class AccessMiddleware
{
    public function resource($resource, $user_resource, $response) 
    {   
        $user_resource = (array)$user_resource;

        if (!in_array($resource, $user_resource,  true)) {
            $response->status = 'error';
            $response->code_error = 401;
            $response->message = 'Recurso de acesso não encontrado';

            return false;
            die();
        }

        return true;
    }
}