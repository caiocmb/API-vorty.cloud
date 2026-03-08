<?php

namespace Src\Controllers;

use Src\Models\TrainingModel;

class TrainingController
{
    private $model,$error_acu;


    public function __construct()
    {
        $this->model = new TrainingModel();
    }

    //recebe os dados e faz o que foi solicitado
    public function Send($response,$dados,$token) 
    {   
        $dados = (object)$dados;
        $token = (object)$token;

        if($dados->method == 'GET')
        {            
            $parm = [];     

            return $this->model->ListarTreinosCompletos($parm,$response,$token);
            die();
        }

       
        // se não encontra nada, retorna erro
        $response->status = 'error';
        $response->code_error = 400;
        $response->message = 'Metodo não permitido ou inexistente';
    
    }
}