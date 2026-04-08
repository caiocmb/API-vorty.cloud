<?php

namespace Src\Models;

use Src\Models\ConnectDB;
use Firebase\JWT\JWT;
use Src\Services\LogServices;

class HistoricalModel extends ConnectDB 
{
    private $conexao,$log;
    public function __construct() 
    {
        $this->conexao = parent::ConnectSaas();
        $this->log = new LogServices();
    }

    public function Buscar($parms, $response, $data_received) : string | bool
    {
        try {

            $company_id = $data_received->company->id;
            $userid = $data_received->uid;

            // nessa primeira listagem vamos retornar o saldo do usuario
            $sql = $this->conexao->prepare("SELECT balance as xp_total FROM tb_app_xp_balance WHERE member_id = :userid AND id_company = :company_id");
            $sql->execute(['userid' => $userid, 'company_id' => $company_id]);
            $saldo_xp = $sql->fetch(\PDO::FETCH_ASSOC);

        

            // aqui vai listar os treinos realizados pelo usuario. Precisava formatar a quebra e os arrays oconforme a consulta SQL
            $sql = $this->conexao->prepare("
                                        SELECT * FROM (
                                        SELECT
                                            -- header do treino
                                            CONCAT(h.id,'') as id,
                                            h.name_training, 
                                            h.initial_date, 
                                            h.final_date, 
                                            null as obs,
                                            h.xp,
                                            h.total_load, 
                                            h.total_minutes,
                                            -- exercicios propostos
                                            e.name_exercise,
                                            e.repetitions as meta,
                                            e.und as unit,
                                            e.seq,
                                            -- exercicios realizados
                                            w.peso,
                                            w.reps,
                                            w.serie
                                        FROM tb_app_training_header h
                                        INNER JOIN tb_app_training_exercises e on e.id_header = h.id 
                                        LEFT JOIN tb_app_training_workout w on w.id_exercise = e.id
                                        WHERE 
                                            h.id_member = :member_id
                                            AND h.id_company = :id_company
                                            AND h.final_date is not null
                                        
                                        UNION ALL 

                                        SELECT
                                            -- header do treino
                                            CONCAT(io.id,'') as id,
                                            CONCAT('XP_IO_',io.tipo) AS name_training, 
                                            io.created_at as initial_date, 
                                            io.data as final_date, 
                                            io.obs as obs,
                                            io.value as xp,
                                            null as total_load, 
                                            null as total_minutes,
                                            -- exercicios propostos
                                            null as name_exercise,
                                            null as meta,
                                            null as unit,
                                            null as seq,
                                            -- exercicios realizados
                                            null as peso,
                                            null as reps,
                                            null as serie
                                        FROM tb_app_xp_inout io
                                        INNER JOIN tb_users u on u.id = io.id_user 
                                        WHERE 
                                            io.id_member = :member_id_io
                                            AND io.id_company = :id_company_io
                                        ) AS G    
                                        ORDER BY
                                            G.initial_date DESC,
                                            G.seq ASC,
                                            G.serie ASC");
            $sql->execute(['member_id' => $userid, 'id_company' => $company_id, 'member_id_io' => $userid, 'id_company_io' => $company_id]);
            $resultados = $sql->fetchAll(\PDO::FETCH_ASSOC);

            // aqui formata conforme a necessidade do front, agrupando os exercicios dentro de cada header de treino e cada execução dentro de cada exercicio.
            $historico = [];

            foreach ($resultados as $row) {
                $treino_id = $row['id'];

                // Verifica se o treino já foi adicionado ao histórico
                if (!isset($historico[$treino_id])) {
                    $historico[$treino_id] = [
                        'id' => $row['id'],
                        'name_training' => $row['name_training'],
                        'initial_date' => $row['initial_date'],
                        'final_date' => $row['final_date'],
                        'obs' => $row['obs'],
                        'xp' => $row['xp'],
                        'total_load' => $row['total_load'],
                        'total_minutes' => $row['total_minutes'],
                        'exercises' => []
                    ];
                }

                // Verifica se o exercício já foi adicionado ao treino
                $exercise_key = $row['name_exercise'] . '_' . $row['meta'] . '_' . $row['unit'];
                if (!isset($historico[$treino_id]['exercises'][$exercise_key])) {
                    $historico[$treino_id]['exercises'][$exercise_key] = [
                        'name_exercise' => $row['name_exercise'],
                        'meta' => $row['meta'],
                        'unit' => $row['unit'],
                        'executions' => []
                    ];
                }       

                // Adiciona a execução ao exercício e formata o peso, só exibe depois da virgula se tiver valor e se for null peso/reps, nao mandar nada
                if ($row['peso'] !== null || $row['reps'] !== null) {
                $historico[$treino_id]['exercises'][$exercise_key]['executions'][] = [
                    'peso' => $row['peso'] !== null ? number_format($row['peso'], 1, ',', '.') : null,
                    'reps' => $row['reps'] !== null ? number_format($row['reps'], 0, ',', '.') : null,
                    'serie' => $row['serie']
                ];  
                }
            }

             // Converte o histórico para um array indexado
             $historico = array_values($historico);

             // Retorna o histórico formatado
             if (!$historico) {
                $response->status = 'success';
                $response->message = 'Nenhum histórico encontrado para este usuário.';
                $response->data = [];
                return true;
            }

            $response->status = 'success';
            $response->message = 'Histórico encontrado com sucesso.';
            $response->data = [
                'xp_total' => $saldo_xp['xp_total'] ?? 0,
                'treinos' => $historico
            ];
            return true;

        } catch (\PDOException $e) {
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro ao executar a consulta no banco de dados: ' . $e->getMessage();
            return false;
        }
    }
    
}