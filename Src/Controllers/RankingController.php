<?php

namespace Src\Controllers;

use Src\Models\RankingModel;

class RankingController
{
    private $model;

    public string $usuario, $senha;

    public function __construct()
    {
        $this->model = new RankingModel();
    }

   /* private function ValidaInput($response,$dados) : bool
    {
        $dados = (object)$dados;

        if(isset($error_acu))
        {
            $response->status = 'error';
            $response->code_error = 411;
            $response->message = 'Campo '.implode(', ',$error_acu).' deve existir e não pode ser vazio';

            return false;
            die();
        }    
        
        return true;

    }*/

    //recebe os dados e faz o que foi solicitado
    public function Send($response,$header,$body) 
    {   
        global $rotas;

        $header = (object)$header;
        $body = (object)$body;

        if($header->method == 'GET')
        {   
            $parm = [];

            if(isset($rotas[1]) && $rotas[1] == 'check_connection')
            {          
                return $this->model->CheckConnectionFriend($parm,$response,$body);
                die();
            }
            else
            {
                return $this->model->Ranking($parm,$response,$body);
                die();
            }
            
        }

        if($header->method == 'POST')
        {   
            $parm = [];
      
            return $this->model->ConnectUser($parm,$response,$body);
            die();

            
        }
       
        // se não encontra nada, retorna erro
        $response->status = 'error';
        $response->code_error = 400;
        $response->message = 'Metodo não permitido ou inexistente';
    
    }
}