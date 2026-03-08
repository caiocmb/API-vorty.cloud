<?php

namespace Src\Models;

use Src\Models\ConnectDB;
use Firebase\JWT\JWT;
use Src\Services\LogServices;

class ProfileModel extends ConnectDB 
{
    private $conexao,$log;
    public function __construct() 
    {
        $this->conexao = parent::ConnectSaas();
        $this->log = new LogServices();
    }

    public function Cadastrar($parms, $response, $token) : string | bool
    {
        try {
            $sql = $this->conexao->prepare("
                UPDATE tb_gym_member 
                SET 
                    foto_app = COALESCE(:foto_app, foto_app),
                    social_name = :social_name 
                WHERE `id_company` = :company_id AND `id` = :userid
            ");

            $executou = $sql->execute([
                'company_id'  => $token->company->id,
                'userid'      => $token->uid,
                'foto_app'    => !empty($parms['social_photo']) ? $parms['social_photo'] : null,
                'social_name' => $parms['social_name']
            ]);

            $this->log->registrarAuditoria($token->uid, $token->company->id, 'PERFIL', 'UPDATE', null, null, json_encode($parms));
            
            if ($executou) {
                // Se a query rodou sem dar Exception, tecnicamente os dados estão íntegros.
                $response->status = 'success';
                $response->code_error = 200;
                $response->message = 'Perfil atualizado com sucesso!';
                return true;
            }

        } catch (\PDOException $e) {
            // Aqui sim é um erro real (falha de conexão, erro de sintaxe, etc)
            //error_log("Erro SQL: " . $e->getMessage());
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro interno ao salvar os dados.';
            return false;
        }

        return false;
    }

    public function AtualizarSenha($parms, $response, $token) : bool
    {
        try {
            // 1. Primeiro buscamos a senha atual do banco para validar
            $stmt = $this->conexao->prepare("SELECT password FROM tb_gym_member WHERE id_company = :company_id AND id = :userid");
            $stmt->execute([
                'company_id' => $token->company->id,
                'userid'     => $token->uid
            ]);
            
            $usuario = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$usuario) {
                $response->status = 'error';
                $response->message = 'Usuário não encontrado.';
                return false;
            }

            // 2. Validamos se a "Senha Atual" enviada bate com a do banco
            // Se suas senhas não forem criptografadas (o que não recomendo), mude para uma comparação simples
            if (!password_verify($parms['current_password'], $usuario->password)) {
                $response->status = 'error';
                $response->message = 'A senha atual está incorreta.';
                return false;
            }

            // 3. Se passou, agora sim fazemos o UPDATE com a nova senha criptografada
            $sql = $this->conexao->prepare("
                UPDATE tb_gym_member 
                SET password = :nova_senha
                WHERE id_company = :company_id AND id = :userid
            ");

            $novaSenhaHash = password_hash($parms['new_password'], PASSWORD_DEFAULT);

            $executou = $sql->execute([
                'company_id' => $token->company->id,
                'userid'     => $token->uid,
                'nova_senha' => $novaSenhaHash
            ]);

            $this->log->registrarAuditoria($token->uid, $token->company->id, 'PERFIL', 'UPDATE', null, null, 'Senha alterada pelo usuário');

            if ($executou) {
                $response->status = 'success';
                $response->code_error = 200;
                $response->message = 'Senha alterada com sucesso!';
                return true;
            }

        } catch (\PDOException $e) {
            error_log("Erro SQL Senha: " . $e->getMessage());
            $response->status = 'error';
            $response->message = 'Erro interno ao processar a nova senha.';
            return false;
        }

        return false;
    }
    
}