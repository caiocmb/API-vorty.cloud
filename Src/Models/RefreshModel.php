<?php

namespace Src\Models;

use Src\Models\ConnectDB;
use Firebase\JWT\JWT;
use Src\Services\LogServices;

class RefreshModel extends ConnectDB
{
    private $conexao,$connsaas,$log;
    public function __construct() 
    {
        $this->conexao = parent::ConnectLog();
        $this->connsaas = parent::ConnectSaas();
        $this->log = new LogServices();
    }

    // Faz a consulta para logar aluno na plataforma
    public function RefreshAlunos($data, $response) : string | bool
    {
        $sql = $this->conexao->prepare("SELECT id, token, expires_at, is_revoked FROM user_refresh_tokens 
            WHERE user_id = :uid AND is_revoked = 0 AND expires_at > NOW() AND company_id = :company ORDER BY created_at DESC LIMIT 1");
        $sql->execute([
            'uid' => $data['uid'],
            'company' => $data['company']
        ]);        

        $tokensNoBanco = $sql->fetchAll(\PDO::FETCH_ASSOC);
        
        //valida se foi encontrado, caso nao, retorna false
        $tokenValido = false;
        foreach ($tokensNoBanco as $row) {
            // 3. Verifica se o token enviado "bate" com o hash do banco
            if (password_verify($data['refresh_token'], $row['token'])) {
                // 4. Verifica se ainda está na validade
                if (strtotime($row['expires_at']) > time()) {
                    $tokenValido = $row; // Sucesso!
                    break;
                }
            }
        }

        if(!$tokenValido) {   

            $response->status = 'error';
            $response->code_error = 401;
            $response->message = 'Token inválido ou expirado.';
            return false;
            die();
        }

        $sql = $this->connsaas->prepare("SELECT 
                                            u.id
                                            ,u.name
                                            ,u.social_name
                                            ,u.cpf
                                            ,u.password
                                            ,date_format(u.date_of_birth,'%d%m%Y') as aniversario
                                            ,u.id_company as company_id
                                            ,e.surname as company_name
                                            ,CASE u.status WHEN 'A' THEN 'Ativo' WHEN 'I' THEN 'Inativo' WHEN 'E' THEN 'Plano Encerrado' ELSE 'Bloqueado' END as status
                                            ,foto_app as foto
                                        FROM tb_gym_member as u 
                                        INNER JOIN tb_company as e on e.id = u.id_company
                                        WHERE u.id = :id and e.cnpj = :company and u.status not in ('I') LIMIT 1");
        $sql->execute([
            'id' => $data['uid'],
            'company' => $data['company']
        ]);        

        $aluno = $sql->fetch(\PDO::FETCH_ASSOC);

        if(!$aluno) 
        {
            //registra log
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
            "company" => [
                "id" => $aluno['company_id'],
                "name" => $aluno['company_name']
            ],
            "resources" => ['alunos'],
        ];

        $encode = JWT::encode($payload, $key, 'HS256');

        // registra novo refresh token
        $refreshToken = bin2hex(random_bytes(32));

        $this->log->registrarRefreshToken($aluno['id'], $data['company'], $refreshToken);
   
        //registra log
        $this->log->registrarLogin($aluno['id'], $aluno['cpf'], $data['company'], true, 'Aluno atualizou o token na plataforma');

        $foto_app = $aluno['foto'] ?? '';
        if(empty(trim($foto_app))){ $foto_app = 'no_picture.webp'; }

        //se deu certo, retorna successo e o token
        $response->status = 'success';
        $response->code_error = 200;
        $response->message = 'Token atualizado com sucesso';
        $response->data = [
            "token" => $encode,
            "refresh_token" => $refreshToken,
            "name" => $aluno['name'],
            "social_name" => $aluno['social_name'],
            "uid" => $aluno['id'],
            "status" => $aluno['status'],
            "photo" => $foto_app
        ];

        return true;

    }
}