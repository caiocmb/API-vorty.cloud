<?php

namespace Src\Controllers;

use Src\Models\AnimalModel;

class AnimalController
{
    private $model;

    public string $usuario, $senha;

    public function __construct()
    {
        $this->model = new AnimalModel();
    }

    private function ValidaInput($response,$dados) : bool
    {
        $dados = (object)$dados;

        //valida se esta recebendo id para alteracao
        if($dados->method == 'PUT')
        {
            /*
            if(!isset($dados->data['id']) or empty(addslashes(trim($dados->data['id']))))
            {
                $error_acu[] = "id";
            }
            */
        }

        if(!isset($dados->data['cod_brinco']) or empty(addslashes(trim($dados->data['cod_brinco']))))
        {
            $error_acu[] = "cod_brinco";
        }

        if(!isset($dados->data['id_propriedade']) or empty(addslashes(trim($dados->data['id_propriedade']))))
        {
            $error_acu[] = "id_propriedade";
        }



        if(isset($error_acu))
        {
            $response->status = 'error';
            $response->code_error = 411;
            $response->message = 'Campo '.implode(', ',$error_acu).' deve existir e não pode ser vazio';

            return false;
            die();
        }    
        
        return true;

    }

    //recebe os dados e faz o que foi solicitado
    public function Send($response,$dados,$user_id) 
    {   
        $dados = (object)$dados;

        if($dados->method == 'GET')
        {
            $parm['cod_brinco'] = '';
            $parm['id_propriedade'] = '';
            $parm['propriedade'] = '';

            if(isset($dados->data['cod_brinco']))
            {
                $parm['cod_brinco'] = addslashes($dados->data['cod_brinco']);
            }

            if(isset($dados->data['id_propriedade']))
            {
                $parm['id_propriedade'] = addslashes($dados->data['id_propriedade']);
            }    
            
            if(isset($dados->data['propriedade']))
            {
                $parm['propriedade'] = addslashes($dados->data['propriedade']);
            }    

            return $this->model->Buscar($parm,$response,$user_id);
            die();
        }

        if($dados->method == 'POST')
        {   
            $validacao = $this->ValidaInput($response,$dados);
        
            if(!$validacao)
            {
                return $validacao;
                die();
            }

            return $this->model->Cadastrar($dados,$response,$user_id);
            die();
        }

        if($dados->method == 'PUT')
        {
            $validacao = $this->ValidaInput($response,$dados);
        
            if(!$validacao)
            {
                return $validacao;
                die();
            }

            return $this->model->Atualizar($dados,$response,$user_id);
            die();
        }

        if($dados->method == 'DELETE')
        {
            $validacao = $this->ValidaInput($response,$dados);
        
            if(!$validacao)
            {
                return $validacao;
                die();
            }

            return $this->model->Excluir($dados,$response,$user_id);
            die();
        }        
    
    }
}