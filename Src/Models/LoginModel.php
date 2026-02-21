<?php

namespace Src\Models;

use Src\Models\ConnectDB;
use Firebase\JWT\JWT;
use Src\Services\LogServices;

class LoginModel extends ConnectDB
{
    private $conexao,$log;
    public function __construct() 
    {
        $this->conexao = parent::ConnectSaas();
        $this->log = new LogServices();
    }

    // Faz a consulta para logar aluno na plataforma
    public function LogarAluno($cpf, $password, $company, $response) : string | bool
    {
        $sql = $this->conexao->prepare("SELECT 
                                            u.id
                                            ,u.name
                                            ,u.cpf
                                            ,u.password
                                            ,date_format(u.date_of_birth,'%d%m%Y') as aniversario
                                            ,u.id_company as company_id
                                            ,e.surname as company_name
                                        FROM tb_gym_member as u 
                                        INNER JOIN tb_company as e on e.id = u.id_company
                                        WHERE u.cpf = :cpf and e.cnpj = :company and u.status = 'A' LIMIT 1");
        $sql->execute([
            'cpf' => $cpf,
            'company' => $company
        ]);        

        $aluno = $sql->fetch(\PDO::FETCH_ASSOC);
        
        //valida se foi encontrado, caso nao, retorna false
        if(!$aluno) 
        {
            //registra log
            $this->log->registrarLogin(null, $cpf, $company, false, 'CPF não encontrado, cnpj errado ou cadastro inativo');

            $response->status = 'error';
            $response->code_error = 401;
            $response->message = 'As credenciais informadas são inválidas.';

            return false;
            die();
        }

        //verifica se a senha ainda está nula, indica o primeiro acesso
        if($aluno['password'] == null)
        {
            $senha_cripto = password_hash($aluno['aniversario'], PASSWORD_DEFAULT);

            // atualiza campos de senha
            $stmt_upd = $this->conexao->prepare("UPDATE tb_gym_member SET password = :senha WHERE id = :id and id_company = :company");
            $stmt_upd->execute([
                'senha' => $senha_cripto,
                'id' => $aluno['id'],
                'company' => $aluno['company_id']
            ]);

            //registra log
            $this->log->registrarLogin($aluno['id'], $cpf, $company, true, 'Primeiro acesso do aluno, senha registrada');
        }
        else
        {
            $senha_cripto = $aluno['password'];
        }

        //varifica a senha
        if(!password_verify($password,$senha_cripto)) 
        {
            //registra log
            $this->log->registrarLogin($aluno['id'], $cpf, $company, false, 'Senha informada no formulário não bate com a senha registrada');

            $response->status = 'error';
            $response->code_error = 401;
            $response->message = 'As credenciais informadas são inválidas.';

            return false;
            die();
        }

        //se chegou aqui, está tudo correto, ele vai gerar o token e retornar
        $key = $_ENV['KEY_JWT'];

        $payload = [
            "exp" => time()+43200,
            "iat" => time(),
            "uid" => $aluno['id'],
            "name" => $aluno['name'],
            "resources" => ['alunos'],
        ];

        $encode = JWT::encode($payload, $key, 'HS256');

        //registra log
        $this->log->registrarLogin($aluno['id'], $cpf, $company, true, 'Aluno logou na plataforma');

        //se deu certo, retorna successo e o token
        $response->status = 'success';
        $response->code_error = 200;
        $response->message = 'Login efetuado com sucesso';
        $response->data = [
            "token" => $encode,
            "name" => $aluno['name'],
            "uid" => $aluno['id']
        ];

        return true;

    }
}