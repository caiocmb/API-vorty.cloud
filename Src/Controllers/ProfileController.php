<?php

namespace Src\Controllers;

use Src\Models\ProfileModel;

class ProfileController
{
    private $model,$error_acu;


    public function __construct()
    {
        $this->model = new ProfileModel();
    }

    private function ValidaInput($response,$dados) : bool
    {
        //$dados = (object)$dados;

        $this->error_acu = [];

        if(!isset($dados->data['user_name']) || empty(trim($dados->data['user_name'])))
        {
            $this->error_acu[] = 'Nome Social';
        }
        
        if(count($this->error_acu) > 0)
        {
            $response->status = 'error';
            $response->code_error = 411;
            $response->message = 'Campo '.implode(', ',$this->error_acu).' deve existir e não pode ser vazio';

            return false;
            die();
        }    
        
        return true;

    }

    //recebe os dados e faz o que foi solicitado
    public function Send($response,$dados,$token) 
    {   
        $dados = (object)$dados;
        $token = (object)$token;

        if($dados->method == 'POST')
        {   
            // valida os dados de entrada
            $validacao = $this->ValidaInput($response,$dados);
        
            if(!$validacao)
            {
                return $validacao;
                die();
            }

            $parm['social_name'] = null;
            $parm['social_photo'] = null;


            
            if(isset($dados->data['user_name']))
            {
                $parm['social_name'] = addslashes($dados->data['user_name']);
            }   

            if(isset($dados->data['photo']))
            {
                $parm['social_photo'] = addslashes($dados->data['photo']);
            }    
            

            return $this->model->Cadastrar($parm,$response,$token);
            die();
        }

        if($dados->method == 'PUT')
        {   
            // 1. Validação de Entrada específica para senha
            if(empty($dados->data['current_password']) || empty($dados->data['new_password'])) {
                $response->status = 'error';
                $response->message = 'Informe a senha atual e a nova senha.';
                return false;
            }

            // Opcional: Validar tamanho mínimo
            if(strlen($dados->data['new_password']) < 6) {
                $response->status = 'error';
                $response->message = 'A nova senha deve ter no mínimo 6 caracteres.';
                return false;
            }

            $parm['current_password'] = $dados->data['current_password'];
            $parm['new_password']     = $dados->data['new_password'];

            // Chama a model de atualização de senha
            return $this->model->AtualizarSenha($parm, $response, $token);
        }
       
        // se não encontra nada, retorna erro
        $response->status = 'error';
        $response->code_error = 400;
        $response->message = 'Metodo não permitido ou inexistente';
    
    }
}