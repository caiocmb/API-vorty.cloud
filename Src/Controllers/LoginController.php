<?php

namespace Src\Controllers;

use Src\Models\LoginModel;
use Src\Helpers\CpfHelper;
use Src\Helpers\DocumentosHelper;

class LoginController
{
    private $model;

    public string $usuario, $senha;

    public function __construct()
    {
        $this->model = new LoginModel();
    }

    private function ValidaCompanyAndApp($response,$dados) : bool
    {
        $dados = (object)$dados;

        //valida se a entrada é post
        if($dados->method <> 'POST')
        {
            $response->status = 'error';
            $response->code_error = 405;
            $response->message = 'Método de entrada incorreto';

            return false;
            die();
        }

        if(!isset($dados->data['application']) or !isset($dados->data['company']))
        {
            $response->status = 'error';
            $response->code_error = 411;
            $response->message = 'Campos aplicação e empresa devem passados por parâmetro';

            return false;
            die();
        }
         
        if(empty(addslashes(trim($dados->data['application']))) or empty(addslashes(trim($dados->data['company']))))
        {
            $response->status = 'error';
            $response->code_error = 411;
            $response->message = 'Campos aplicação e empresa devem ser preenchidos';

            return false;
            die();
        }

        // valida se é um cnpj valido
        if(!DocumentosHelper::validarCnpj($dados->data['company']))
        {
            $response->status = 'error';
            $response->code_error = 411;
            $response->message = 'CNPJ da empresa inválido';

            return false;
            die();
        }

        // valida se a aplicação existe
        $apps = ['alunos'];

        if(!in_array(trim($dados->data['application']), $apps)) 
        {
            $response->status = 'error';
            $response->code_error = 404;
            $response->message = 'Aplicação não encontrada!';

            return false;
            die();
        }

        return true;
    }

    // valida dados de entrada da aplicação de alunos
    private function ValidaInputAlunos($response,$dados) : bool
    {
        $dados = (object)$dados;

        //valida se a entrada é post
        if($dados->method <> 'POST')
        {
            $response->status = 'error';
            $response->code_error = 405;
            $response->message = 'Método de entrada incorreto';

            return false;
            die();
        }

        //valida se foi preenchido e-mail e senha
        if(!isset($dados->data['cpf']) or !isset($dados->data['password']))
        {
            $response->status = 'error';
            $response->code_error = 411;
            $response->message = 'Campos cpf e senha devem ser passados por parâmetro';

            return false;
            die();
        }       
        
        if(empty(addslashes(trim($dados->data['cpf']))) or empty(addslashes(trim($dados->data['password']))))
        {
            $response->status = 'error';
            $response->code_error = 411;
            $response->message = 'Campos cpf e senha devem ser preenchidos';

            return false;
            die();
        }

        if(!DocumentosHelper::validarCpf($dados->data['cpf']))
        {
            $response->status = 'error';
            $response->code_error = 411;
            $response->message = 'CPF inválido';

            return false;
            die();
        }

        return true;

    }

    //recebe os dados e tenta fazer login
    public function FazLogin($response,$dados) : bool
    {   
        // valida se existe aplicação e empresa, se não já retorna erro
        $validacao_app = $this->ValidaCompanyAndApp($response,$dados);
        if(!$validacao_app)
        {
            return $validacao_app;
            die();
        }

        // extratifica os dados para saber qual aplicacao tratar
        $dados = (object)$dados;

        // faz login na aplicação 'alunos'
        if($dados->data['application'] == 'alunos')
        {
            $validacao = $this->ValidaInputAlunos($response,$dados);
        
            if(!$validacao)
            {
                return $validacao;
                die();
            }        

            $vowels = array("-",".");

	        $cpf = str_replace($vowels, "",trim(addslashes($dados->data['cpf'])));
            $password = addslashes($dados->data['password']);
            $company = addslashes($dados->data['company']);

            return $this->model->LogarAluno($cpf, $password, $company, $response);
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