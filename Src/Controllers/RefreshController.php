<?php

namespace Src\Controllers;

use Src\Models\RefreshModel;

class RefreshController
{
    private $model;

    public function __construct()
    {
        $this->model = new RefreshModel();
    }

    //recebe os dados e tenta fazer login
    public function RefreshToken($response,$dados) : bool
    {       

        // extratifica os dados para saber qual aplicacao tratar
        $dados = (object)$dados;

        $data['refresh_token'] = addslashes($dados->data['refresh_token']);
        $data['company'] = addslashes($dados->data['company']);
        $data['uid'] = addslashes($dados->data['uid']);
        $data['application'] = addslashes($dados->data['application']);

        if(empty($data['refresh_token']) || empty($data['company']) || empty($data['uid']) || empty($data['application']))
        {
            $response->status = 'error';
            $response->code_error = 411;
            $response->message = 'Campo refresh_token, company, uid e application devem existir e não podem ser vazios';
            return false;
            die();
        }  
        
        if($data['application'] == 'alunos')
        {
            return $this->model->RefreshAlunos($data, $response);
            die();
        }

        // erro generico para o caso de passar acima sem retorno nenhum
        $response->status = 'error';
        $response->code_error = 404;
        $response->message = 'Nenhuma aplicação encontrada ou regra de validação ativa. Favor contatar o suporte';

        return false;
        die();    
    }
}