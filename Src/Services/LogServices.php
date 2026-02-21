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

    // log para auditoria
    public function registrarAuditoria(
        $usuarioId,
        $modulo,
        $acao,
        $registroId = null,
        $antes = null,
        $depois = null
    ) {
        $sql = "INSERT INTO log_auditoria
                (user_id, modulo, acao, registro_id, antes, depois, ip, user_agent, criado_em)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->conexao->prepare($sql);
        $stmt->execute([
            $usuarioId,
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