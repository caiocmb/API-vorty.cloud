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
                INNER JOIN tb_gym_member g ON g.id = t.id_member AND g.id_company = t.id_company
                WHERE t.id_member = :uid AND t.id_company = :cid AND g.status = 'A'
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

                // aqui vamos validar se existe treino aberto para o treino, se existir, ele mostra a ação de continuar, se não existir, ele mostra a ação de iniciar. Para isso, precisamos puxar da tabela tb_app_training_header onde id_member = user_id e id_training = treino_id e final_date is null, se encontrar, mostra continuar, se não encontrar, mostra iniciar.
                $sqlHeader = $this->conexao->prepare("SELECT id FROM tb_app_training_header WHERE id_member = :uid AND id_training = :treino_id AND id_company = :cid AND final_date IS NULL and date_format(initial_date, '%Y-%m-%d') = date_format(now(), '%Y-%m-%d')");
                $sqlHeader->execute(['uid' => $user_id, 'treino_id' => $treino['id'], 'cid' => $company_id]);
                $headerRow = $sqlHeader->fetch(\PDO::FETCH_ASSOC);  

                // aqui alem de definir se é iniciar ou continuar, ele também verifica se o treino tem exercicios, se tiver exercicios, ele mostra iniciar ou continuar, se não tiver exercicios, ele não mostra nada.
                if ($headerRow) {
                    $acao = "Continuar";
                } else {
                    $acao = !empty($exercicios) ? "Iniciar" : null;
                }
                
                $treinosFormatados[] = [
                    "titulo" => $treino['titulo'],
                    "id" => base64_encode($treino['id']),
                    "acao"   => $acao,
                    "exercicios" => $exercicios
                ];
            }

            // 4. Montar Resposta Final
            $response->status = 'success';
            $response->code_error = 200;
            $response->message = 'Treinos listados com sucesso';
            $response->data = [
                "observacao" => $obsRow['obs'] ?? null,
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