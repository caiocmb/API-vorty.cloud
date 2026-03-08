<?php 
namespace Src\Services;

use Src\Models\ConnectDB;

class LogServices extends ConnectDB
{
    private $conexao;
    public function __construct() 
    {
        $this->conexao = parent::ConnectLog();
    }

    // log para login
    public function registrarLogin($usuarioId, $cpf, $company, $sucesso, $mensagem = null)
    {
        $cpf = preg_replace('/\D/', '', $cpf); // normaliza CPF
        $secret = $_ENV['LOG_SECRET_KEY'];
        $cpfHash = hash_hmac('sha256', $cpf, $secret);

        $sql = "INSERT INTO log_login 
                (company,user_id, cpf_hash, ip, user_agent, sucesso, mensagem, criado_em)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->conexao->prepare($sql);
        $stmt->execute([
            $company,
            $usuarioId,
            $cpfHash,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $sucesso ? 1 : 0,
            $mensagem
        ]);
    }

    // grava refresh tokan tabela de logs
    public function registrarRefreshToken($usuarioId, $company, $token)
    {
        // Captura o IP do usuário (funciona na maioria dos servidores)
        $ipUsuario = $_SERVER['REMOTE_ADDR'] ?? null;

        // 1. Limpeza opcional de tokens expirados para manter a performance
        $sqlDelete = "DELETE FROM user_refresh_tokens WHERE expires_at < NOW()";
        $this->conexao->prepare($sqlDelete)->execute();

        // 2. O Insert com todos os campos da tabela que criamos
        $sql = "INSERT INTO user_refresh_tokens (user_id, company_id, token, expires_at, ip_address) 
                VALUES (:uid, :company, :token, :exp, :ip)";

        $stmt = $this->conexao->prepare($sql);
        
        return $stmt->execute([
            'uid'     => $usuarioId,
            'company' => $company,
            'token'   => password_hash($token, PASSWORD_DEFAULT), // Hash para segurança
            'exp'     => date('Y-m-d H:i:s', strtotime('+7 days')),
            'ip'      => $ipUsuario
        ]);
    }

    // log para auditoria
    public function registrarAuditoria(
        $usuarioId,
        $company,
        $modulo,
        $acao,
        $registroId = null,
        $antes = null,
        $depois = null
    ) {
        $sql = "INSERT INTO log_auditoria
                (user_id, company, modulo, acao, registro_id, antes, depois, ip, user_agent, criado_em)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->conexao->prepare($sql);
        $stmt->execute([
            $usuarioId,
            $company,
            $modulo,
            $acao,
            $registroId,
            $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
            $depois ? json_encode($depois, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

}