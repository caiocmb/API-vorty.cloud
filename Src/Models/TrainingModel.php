<?php

namespace Src\Models;

use Src\Models\ConnectDB;
use Firebase\JWT\JWT;
use Src\Services\LogServices;

class TrainingModel extends ConnectDB 
{
    private $conexao,$log;
    public function __construct() 
    {
        $this->conexao = parent::ConnectSaas();
        $this->log = new LogServices();
    }

    public function ListarTreinosCompletos($parms, $response, $token): bool
    {
        try {
            $company_id = $token->company->id;
            $user_id = $token->uid;

            // 1. Buscar Observação Global
            $sqlObs = $this->conexao->prepare("SELECT obs FROM tb_trainings_obs WHERE id_member = :uid /*AND id_company = :cid*/ LIMIT 1");
            $sqlObs->execute(['uid' => $user_id/*, 'cid' => $company_id*/]);
            $obsRow = $sqlObs->fetch(\PDO::FETCH_ASSOC);

            // 2. Buscar Lista de Treinos
            $sqlTreinos = $this->conexao->prepare("
                SELECT t.id, m.name as titulo 
                FROM tb_trainings t
                INNER JOIN tb_muscles m ON m.id = t.treino_nome AND m.id_company = t.id_company
                WHERE t.id_member = :uid AND t.id_company = :cid
                ORDER BY t.seq ASC
            ");
            $sqlTreinos->execute(['uid' => $user_id, 'cid' => $company_id]);
            $listaTreinos = $sqlTreinos->fetchAll(\PDO::FETCH_ASSOC);

            $treinosFormatados = [];

            // 3. Buscar Exercícios de cada Treino
            foreach ($listaTreinos as $treino) {
                $sqlEx = $this->conexao->prepare("
                    SELECT 
                        e.name as nome,
                        r.name as repeticoes,
                        CONCAT('https://gym.vorty.cloud/dist/img/exercises/', e.img_model) as exemplo
                    FROM tb_exercises_member m
                    INNER JOIN tb_exercises e ON e.id = m.nome AND e.id_company = m.id_company
                    INNER JOIN tb_repeat r ON r.id = m.repeticoes AND r.id_company = m.id_company
                    WHERE m.treino_id = :treino_id AND m.id_company = :cid
                    ORDER BY m.seq ASC
                ");
                $sqlEx->execute(['treino_id' => $treino['id'], 'cid' => $company_id]);

                $exercicios = $sqlEx->fetchAll(\PDO::FETCH_ASSOC);
                
                $treinosFormatados[] = [
                    "titulo" => $treino['titulo'],
                    "acao"   => !empty($exercicios) ? "Iniciar" : null,
                    "exercicios" => $exercicios
                ];
            }

            // 4. Montar Resposta Final
            $response->status = 'success';
            $response->code_error = 200;
            $response->message = 'Treinos listados com sucesso';
            $response->data = [
                "observacao" => $obsRow['obs'] ?? "Sem observações",
                "treinos" => $treinosFormatados
            ];

            return true;

        } catch (\PDOException $e) {
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro ao buscar treinos: ' . $e->getMessage();
            return false;
        }
    }

  
    
}