<?php

namespace Src\Models;

use Src\Models\ConnectDB;
use Firebase\JWT\JWT;
use GrahamCampbell\ResultType\Success;
use Src\Services\LogServices;

class WorkoutModel extends ConnectDB 
{
    private $conexao,$log;
    public function __construct() 
    {
        $this->conexao = parent::ConnectSaas();
        $this->log = new LogServices();
    }

    // vou criar uma função privada para calcular os XP do treino. A regra é, se fez qualquer serie do exercicio, já ganha o XP total do exercicio.
    // o valor do XP fica na tb_exercise_category coluna "xp". O relacionameno é feito pela tb_exercises.id_category. 
    // o select precisa verificar na tabela tb_app_training_workout pela coluna id_exercise que relaciona com a id da tb_app_training_exercises. Se existe na workout, ai sim computa o xp.
    private function calcularXP($id_header, $company_id): int
    {
        $sql = $this->conexao->prepare("SELECT SUM(c.xp) as total_xp
                                        FROM tb_app_training_exercises te
                                        INNER JOIN tb_exercises e ON e.id = te.id_exercise and e.id_company = :company_id
                                        INNER JOIN tb_exercise_category c ON c.id =  e.id_category and c.id_company = e.id_company
                                        WHERE te.id_header = :id_header 
                                        AND EXISTS (
                                            SELECT 1 
                                            FROM tb_app_training_workout tw 
                                            WHERE tw.id_exercise = te.id and peso is not null and reps is not null and checked = 1
                                        )");
        $sql->execute(['id_header' => $id_header, 'company_id' => $company_id]);
        $result = $sql->fetch(\PDO::FETCH_ASSOC);
        return $result['total_xp'] ?? 0;
    }

    // função privada para pegar o tempo de descanso do exercicio pelo ID da tabela tb_app_training_workout.
    private function getTempoDescansoWorkoutID($exercise_id, $company_id): int
    {
        $sql = $this->conexao->prepare("SELECT c.rest_time_seconds
                                        FROM tb_app_training_exercises te
                                        INNER JOIN tb_exercises e ON e.id = te.id_exercise and e.id_company = :company_id
                                        INNER JOIN tb_exercise_category c ON c.id =  e.id_category and c.id
                                        WHERE te.id = :exercise_id");
        $sql->execute(['exercise_id' => $exercise_id, 'company_id' => $company_id]);
        $result = $sql->fetch(\PDO::FETCH_ASSOC);
        return $result['rest_time_seconds'] ?? 0;
    }

    // funcao para somar xp na tabela de saldo do usuario, tb_app_xp_balance, pega os campos `member_id`, `id_company`, `balance`, se nao existir, cria uma nova linha, se existir, atualiza com o saldo
    private function adicionarXPBalance($member_id, $company_id, $xp_ganho)
    {
        // primeiro verifica se existe um registro para o usuario
        $sqlvrf = $this->conexao->prepare("SELECT balance FROM tb_app_xp_balance WHERE member_id = :member_id AND id_company = :company_id");
        $sqlvrf->execute(['member_id' => $member_id, 'company_id' => $company_id]);
        $balance = $sqlvrf->fetch(\PDO::FETCH_ASSOC);

        if ($balance) {
            // se existir, atualiza o saldo somando o xp ganho
            $novo_balance = $balance['balance'] + $xp_ganho;
            $sqlupdate = $this->conexao->prepare("UPDATE tb_app_xp_balance SET balance = :balance WHERE member_id = :member_id AND id_company = :company_id");
            $sqlupdate->execute(['balance' => $novo_balance, 'member_id' => $member_id, 'company_id' => $company_id]);
        } else {
            // se não existir, cria um novo registro com o xp ganho
            $sqlinsert = $this->conexao->prepare("INSERT INTO tb_app_xp_balance (member_id, id_company, balance) VALUES (:member_id, :company_id, :balance)");
            $sqlinsert->execute(['member_id' => $member_id, 'company_id' => $company_id, 'balance' => $xp_ganho]);
        }
    }

    // calcula tonelagem e minutos de cardio do treino
    private function calcularTonelagem($user_id, $treino_id, $company_id, $id_header = null): array
    {
        if($id_header != null)
        {
            $header_condition = "AND hd.id = '".$id_header."'";
            
        } else {
            $header_condition = "AND hd.final_date IS NULL";
        }

        // aqui calcula a tonelagem do treino, que é a soma do peso x reps de todos os exercicios do treino. Precisa puxar da tabela tb_app_training_workout e fazer a multiplicação de peso x reps e somar tudo. Um detalhe que precisa verificar a unidade se é kg ou min, se for kg, soma com kg, se for min, soma com min, isso é devido ao exercicio ser cardi ou peso.
        $sqltonelagem = $this->conexao->prepare("SELECT 
                                                    SUM(CASE WHEN te.und = 'kg' THEN tw.peso * tw.reps ELSE 0 END) as tonelagem,
                                                    SUM(CASE WHEN te.und = 'min' THEN tw.peso * tw.reps ELSE 0 END) as tempo
                                                FROM tb_app_training_workout tw
                                                INNER JOIN tb_app_training_exercises te ON te.id = tw.id_exercise
                                                INNER JOIN tb_app_training_header hd on hd.id = te.id_header
                                                WHERE hd.id_member = :uid AND hd.id_training = :treino_id AND hd.id_company = :company_id ".$header_condition);
        $sqltonelagem->execute(['uid' => $user_id, 'treino_id' => $treino_id, 'company_id' => $company_id]);
        $tonelagem = $sqltonelagem->fetch(\PDO::FETCH_ASSOC);

        //se nao existir, retorna 0 para embos
        return [
            'tonelagem' => $tonelagem['tonelagem'] ?? 0,
            'tempo' => $tonelagem['tempo'] ?? 0
        ];
    }

    // sanitização dos workout
    private function SanitizaWorkout($user_id, $treino_id, $company_id)
    {
        // se existe checked igual a 0 e peso e reps diferente de null, precisa passar tudo para 1
        $sqlupdate = $this->conexao->prepare("UPDATE tb_app_training_workout tw
                                                INNER JOIN tb_app_training_exercises te ON te.id = tw.id_exercise
                                                INNER JOIN tb_app_training_header hd on hd.id = te.id_header
                                                SET tw.checked = 1
                                                WHERE hd.id_member = :uid AND hd.id = :treino_id AND hd.id_company = :company_id AND hd.final_date is null AND tw.checked = 0 AND tw.peso IS NOT NULL AND tw.reps IS NOT NULL");
        $sqlupdate->execute(['uid' => $user_id, 'treino_id' => $treino_id, 'company_id' => $company_id]);
        
        //aqui precisa listar todos os workouts com peso e reps null e se existir, excluir, usando id_exercises e serie como chave
        $sqlvrfnull = $this->conexao->prepare("SELECT tw.id_exercise, tw.serie FROM tb_app_training_workout tw
                                                INNER JOIN tb_app_training_exercises te ON te.id = tw.id_exercise
                                                INNER JOIN tb_app_training_header hd on hd.id = te.id_header
                                                WHERE hd.id_member = :uid AND hd.id = :treino_id AND hd.id_company = :company_id AND hd.final_date is null AND (tw.peso IS NULL OR tw.reps IS NULL)");     

        $sqlvrfnull->execute(['uid' => $user_id, 'treino_id' => $treino_id, 'company_id' => $company_id]);
        $workouts_null = $sqlvrfnull->fetchAll(\PDO::FETCH_ASSOC);

        foreach($workouts_null as $workout_null)
        {
            $sqldelete = $this->conexao->prepare("DELETE FROM tb_app_training_workout WHERE id_exercise = :id_exercise AND serie = :serie");
            $sqldelete->execute(['id_exercise' => $workout_null['id_exercise'], 'serie' => $workout_null['serie']]);
        }

        return true;
    }

    public function workout($parms, $response, $token): bool
    {
        try {
            $company_id = $token->company->id;
            $user_id = $token->uid;
            $treino_id = $parms[0];

            // verifica se existe treino para o usuario no mesmo dia e se o treino já foi finalizado ou não
            $sqlvrf = $this->conexao->prepare("SELECT * FROM tb_app_training_header WHERE id_member = :uid AND id_training = :treino_id AND date_format(initial_date, '%Y-%m-%d') = date_format(NOW(), '%Y-%m-%d') AND final_date is null AND id_company = :company_id LIMIT 1");
            $sqlvrf->execute(['uid' => $user_id, 'treino_id' => $treino_id, 'company_id' => $company_id]);
            $vrftreino = $sqlvrf->fetch(\PDO::FETCH_ASSOC);

            // verifica se não existe treino de dias anteriores em aberto, se existir, finaliza ele com 4 hrs a mais da data de inicio. Se também houver pontuação, já soma e finaliza
            $sqlvrf2 = $this->conexao->prepare("SELECT * FROM tb_app_training_header WHERE id_member = :uid AND date_format(initial_date, '%Y-%m-%d') < date_format(NOW(), '%Y-%m-%d') AND final_date is null AND id_company = :company_id");
            $sqlvrf2->execute(['uid' => $user_id, 'company_id' => $company_id]);
            $treinos_abertos = $sqlvrf2->fetchAll(\PDO::FETCH_ASSOC);

            foreach($treinos_abertos as $treino)
            {
                // aqui calcula a tonelagem e tempo do treino, se for zero, ele exclui, se for maior, ele finaliza com 4 hrs do inicio
                $tonelagem = $this->calcularTonelagem($user_id, $treino['id_training'], $company_id, $treino['id']);

                if($tonelagem['tonelagem'] == 0 && $tonelagem['tempo'] == 0)
                {
                    $sqldelete = $this->conexao->prepare("DELETE FROM tb_app_training_header WHERE id = :id");
                    $sqldelete->execute(['id' => $treino['id']]);
                    continue;
                }

                // aqui já calcula o XP do treino antes de finalizar
                $xp = $this->calcularXP($treino['id'], $company_id);   
                
                /* se existe checked igual a 0 e peso e reps diferente de null, precisa passar tudo para 1
                aqui precisa listar todos os workouts com peso e reps null e se existir, excluir, usando id_exercises e serie como chave */
                $this->SanitizaWorkout($user_id, $treino['id'], $company_id);

                // aqui finaliza o treino com a data de inicio + 4 hrs e já atualiza o XP do treino
                $final_date = date('Y-m-d H:i:s', strtotime($treino['initial_date'] . ' +4 hours'));
                $sqlupdate = $this->conexao->prepare("UPDATE tb_app_training_header SET final_date = :final_date, xp = :xp, total_load = :tonelagem, total_minutes = :total_minutes WHERE id = :id");
                $sqlupdate->execute(['final_date' => $final_date, 'xp' => $xp, 'tonelagem' => $tonelagem['tonelagem'], 'total_minutes' => $tonelagem['tempo'], 'id' => $treino['id']]);

                // se deu tudo certo, aqui já adiciona o XP do treino no saldo do usuario
                $this->adicionarXPBalance($user_id, $company_id, $xp);
            }

            // se não existe, ele cria um novo registro para o treino e copia os exercicios do treino para o treino do usuario
            if(!$vrftreino)
            {   
                $insert_header = $this->conexao->prepare("INSERT INTO tb_app_training_header (`id_company`, `id_training`, `name_training`, `initial_date`, `id_member`) 
                                                          SELECT t.id_company,t.id,m.name,now(),t.id_member FROM tb_trainings t
                                                          INNER JOIN tb_muscles m on m.id = t.treino_nome and m.id_company = t.id_company
                                                          WHERE t.id = :treino_id and t.id_company = :company_id and t.id_member = :uid");
                $insert_header->execute(['treino_id' => $treino_id, 'company_id' => $company_id, 'uid' => $user_id]);
                $headerId = $this->conexao->lastInsertId();  // ID gerado para tb_app_training_header

                $insert_exercises = $this->conexao->prepare("INSERT INTO tb_app_training_exercises (`id_header`, `id_exercise`, `name_exercise`, `repetitions`, `und`, `seq`) 
                    SELECT :headerId, e.id as id_exercise, e.name, r.name as repeticoes, e.und, em.seq 
                    FROM tb_exercises_member em
                    INNER JOIN tb_exercises e ON e.id = em.nome and e.id_company = em.id_company
                    INNER JOIN tb_repeat r ON r.id = em.repeticoes and r.id_company = em.id_company
                    WHERE em.treino_id = :treino_id and em.id_member = :uid and em.id_company = :company_id");
                
                $insert_exercises->execute([
                    'headerId' => $headerId,  // Usando o ID do header
                    'treino_id' => $treino_id,
                    'uid' => $user_id,
                    'company_id' => $company_id
                ]);
                
                // Buscar os dados do header criado para montar a resposta
                $sqlvrf = $this->conexao->prepare("SELECT * FROM tb_app_training_header WHERE id_member = :uid AND id = :treino_id AND date_format(initial_date, '%Y-%m-%d') = date_format(NOW(), '%Y-%m-%d') AND final_date is null AND id_company = :company_id LIMIT 1");
                $sqlvrf->execute(['uid' => $user_id, 'treino_id' => $headerId, 'company_id' => $company_id]);
                $vrftreino = $sqlvrf->fetch(\PDO::FETCH_ASSOC);
            }  
            
            $sqlexer = $this->conexao->prepare("SELECT 
                                                    te.id,
                                                    te.name_exercise as nome,
                                                    te.repetitions as protocolo,
                                                    c.xp,
                                                    c.rest_time_seconds as rest,
                                                    te.und,
                                                    CONCAT('https://gym.vorty.cloud/dist/img/exercises/', e.img_model) as img
                                                FROM tb_app_training_exercises te
                                                INNER JOIN tb_exercises e ON e.id = te.id_exercise and e.id_company = :company_id
                                                INNER JOIN tb_exercise_category c ON c.id =  e.id_category and c.id_company = e.id_company
                                                WHERE te.id_header = :treino_id 
                                                ORDER BY te.seq ASC");
            $sqlexer->execute(['treino_id' => $vrftreino['id'], 'company_id' => $company_id]);
            $exercicios = $sqlexer->fetchAll(\PDO::FETCH_ASSOC);

            // 4. Montar Resposta Final
            $response->status = 'success';
            $response->code_error = 200;
            $response->message = 'Treino listado com sucesso';
            $response->data = [
                    'id_treino' => $vrftreino['id'],
                    'nome' => $vrftreino['name_training'],
                    'data_inicio' => $vrftreino['initial_date'], 
                    'usuario_xp' => $vrftreino['xp'], 
                    'exercicios' => $exercicios
                ];

            return true;

        } catch (\PDOException $e) {
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro ao buscar treinos: ' . $e->getMessage();
            return false;
        }
    }    
    
    // aqui vamos criar a função para listar o historico de exercicios do usuario, puxando da tabela tb_app_training_workout, onde tem o id do exercicio.
    public function ListarHistoricoExercicios($parms, $dados, $response, $token) 
    {
        try {
            $company_id = $token->company->id;
            $user_id = $token->uid;
            $treino_id = $parms[0];
            $exercise_id = $dados->data['ex_id'];
            $serie = $dados->data['serie'];

            // aqui a tb_app_training_workout vai fazer o vinculo com a tb_app_training_exercises para pegar os dados de data do exercicio.
            // precisa retornar essa estrutura, 1 exercicio por vez ['history' => ['peso' => '200', 'reps' => '12'.$dados->data['serie'],'und' => 'kg']]
            $sql = $this->conexao->prepare("SELECT 
                                                tw.peso,
                                                tw.reps,
                                                te.und
                                            FROM tb_app_training_workout tw
                                            INNER JOIN tb_app_training_exercises te ON te.id = tw.id_exercise
                                            INNER JOIN tb_app_training_header hd on hd.id = te.id_header
                                            WHERE hd.id_member = :uid AND te.id_exercise = (SELECT id_exercise FROM tb_app_training_exercises WHERE id = :exercise_id) AND tw.serie = :serie AND hd.id_company = :company_id AND hd.final_date is not null
                                            ORDER BY hd.initial_date DESC LIMIT 1");
            $sql->execute(['uid' => $user_id, 'exercise_id' => $exercise_id, 'company_id' => $company_id, 'serie' => $serie]);
            $exercicios = $sql->fetch(\PDO::FETCH_ASSOC);

            // aqui formatamos o peso para pegar apenas a parte inteira, sem o decimal.
            if($exercicios && isset($exercicios['peso'])) {
                $exercicios['peso'] = floor($exercicios['peso']);
            }

            // Montar Resposta Final
            $response->status = 'success';
            $response->code_error = 200;
            $response->message = 'Histórico de exercícios listado com sucesso';
            $response->data = [
                    'history' => $exercicios
                ];

            return true;

        } catch (\PDOException $e) {
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro ao buscar histórico de exercícios: ' . $e->getMessage();
            return false;
        }
    }

    // aqui vamos listar os today sets.
    public function ListarTodaySets($parms, $dados, $response, $token) 
    {
        try {
            $company_id = $token->company->id;
            $user_id = $token->uid;         
            $exercise_id = $dados->data['ex_id'];
            $sql = $this->conexao->prepare("SELECT 
                                                tw.peso,
                                                tw.reps,
                                                tw.checked
                                            FROM tb_app_training_workout tw
                                            INNER JOIN tb_app_training_exercises te ON te.id = tw.id_exercise
                                            INNER JOIN tb_app_training_header hd on hd.id = te.id_header
                                            WHERE hd.id_member = :uid AND tw.id_exercise = :exercise_id AND hd.id_company = :company_id AND date_format(hd.initial_date, '%Y-%m-%d') = date_format(NOW(), '%Y-%m-%d')
                                            ORDER BY tw.serie ASC");
            $sql->execute(['uid' => $user_id, 'exercise_id' => $exercise_id, 'company_id' => $company_id]);
            $sets = $sql->fetchAll(\PDO::FETCH_ASSOC);  

            // aqui formatamos o peso para pegar apenas a parte inteira, se tiver decimal, exibe separado por virgula.
            foreach ($sets as &$set) {
                if (isset($set['peso'])) {
                    $set['peso'] = rtrim(rtrim(number_format($set['peso'], 2, '.', ''), '0'), '.');
                }
            }
            

            // Montar Resposta Final
            $response->status = 'success';      
            $response->code_error = 200;
            $response->message = 'Exercicios executados listado com sucesso';
            $response->data = [
                    'sets' => $sets
                ];      

            return true;
        } catch (\PDOException $e) {
            $response->status = 'error';    
            $response->code_error = 500;
            $response->message = 'Erro ao buscar exercícios executados: ' . $e->getMessage();

            return false;       
        }
    }

    // aqui vamos criar a função que vai adicionar series em branco, conforme cria na aplicaçáo. Vai buscar a ultima serie do banco e ir adicionando.
    public function AddSet($parms, $dados, $response, $token) 
    {
        // essa função vai apenas adicionar uma nova serie em branco, ou seja, sem peso e reps, apenas com o id do exercicio e a serie. O preenchimento do peso e reps é feito na função SalvarTodaySets.
        try {
            $company_id = $token->company->id;
            $user_id = $token->uid;         
            $exercise_id = $dados->data['ex_id'];

            // aqui precisa buscar a ultima serie do exercicio para adicionar uma nova serie em branco com o numero da ultima serie + 1.
            $sqlvrf = $this->conexao->prepare("SELECT tw.serie FROM tb_app_training_workout tw
                                                INNER JOIN tb_app_training_exercises te ON te.id = tw.id_exercise
                                                INNER JOIN tb_app_training_header hd on hd.id = te.id_header
                                                WHERE hd.id_member = :uid AND tw.id_exercise = :exercise_id AND hd.id_company = :company_id AND hd.final_date is null
                                                ORDER BY tw.serie DESC LIMIT 1");
            $sqlvrf->execute(['uid' => $user_id, 'exercise_id' => $exercise_id, 'company_id' => $company_id]);
            $last_serie = $sqlvrf->fetch(\PDO::FETCH_ASSOC);

            $new_serie = isset($last_serie['serie']) ? $last_serie['serie'] + 1 : 1;

            // aqui insere a nova serie em branco
            $sqlinsert = $this->conexao->prepare("INSERT INTO tb_app_training_workout (id_exercise, serie) VALUES (:exercise_id, :serie)");
            $sqlinsert->execute(['exercise_id' => $exercise_id, 'serie' => $new_serie]);

            // Montar Resposta Final
            $response->status = 'success';      
            $response->code_error = 200;
            $response->message = 'Nova série adicionada com sucesso';
            $response->data = [
                    'success' => true,
                    'message' => 'Nova serie criada com sucesso'
                ];
            return true;
        } catch (\PDOException $e) {
            $response->status = 'error';    
            $response->code_error = 500;
            $response->message = 'Erro ao adicionar nova série: ' . $e->getMessage();
            return false;           
        }
    }

    // salva os sets do treino. Aqui é apenas inserção, quando vai atualizar, ele exclui o antigo e salva um novo, mas será tratado em outra função.
    public function SalvarTodaySets($parms, $dados, $response, $token) 
    {
        try {
            $company_id = $token->company->id;
            $user_id = $token->uid; 
            $exercise_id = $dados->data['ex_id'];
            $serie = $dados->data['serie'];
            $peso = $dados->data['peso'];
            $reps = $dados->data['reps'];

            // aqui precisa verificar se id_exercise e serie existe, se existir, atualiza.
            $sqlvrf = $this->conexao->prepare("SELECT tw.id_exercise, te.id_exercise AS id_ex_gym FROM tb_app_training_workout tw
                                                    INNER JOIN tb_app_training_exercises te ON te.id = tw.id_exercise
                                                    INNER JOIN tb_app_training_header hd on hd.id = te.id_header
                                                    WHERE hd.id_member = :uid AND tw.id_exercise = :exercise_id AND tw.serie = :serie AND hd.id_company = :company_id AND hd.final_date IS NULL and tw.checked = 0 LIMIT 1");
            $sqlvrf->execute(['uid' => $user_id, 'exercise_id' => $exercise_id, 'company_id' => $company_id, 'serie' => $serie]);
            $vrfset = $sqlvrf->fetch(\PDO::FETCH_ASSOC);

            if($vrfset)
            {
                $sqlupdate = $this->conexao->prepare("UPDATE tb_app_training_workout SET peso = :peso, reps = :reps, checked = 1 WHERE id_exercise = :id and serie = :serie");
                $sqlupdate->execute(['peso' => $peso, 'reps' => $reps, 'id' => $vrfset['id_exercise'], 'serie' => $serie]);
            } else {
                $response->status = 'error';
                $response->code_error = 404;
                $response->message = 'Série não encontrada ou não disponível para atualização';
            }           

            // aqui precisa recalcular o XP do treino usando a função calcularXP.
            $sqlheader = $this->conexao->prepare("SELECT id_header FROM tb_app_training_exercises WHERE id = :id_exercise");
            $sqlheader->execute(['id_exercise' => $exercise_id]);
            $header = $sqlheader->fetch(\PDO::FETCH_ASSOC); 
            if($header) {
                $xp = $this->calcularXP($header['id_header'], $company_id);
                $sqlupdate = $this->conexao->prepare("UPDATE tb_app_training_header SET xp = :xp WHERE id = :id");
                $sqlupdate->execute(['xp' => $xp, 'id' => $header['id_header']]);
            }

            // aqui pega o tempo de descanso do exercicio usando a função getTempoDescanso.
            $rest = $this->getTempoDescansoWorkoutID($exercise_id, $company_id);

            // agora montamos a resposta com ['success' => true, 'novo_xp' => $storage['usuario_xp']]
            $response->status = 'success';
            $response->code_error = 200;        
            $response->message = 'Set salvo com sucesso';
            $response->data = [
                    'success' => true,
                    'novo_xp' => $xp,
                    'rest' => $rest
                ];

            return true;
        } catch (\PDOException $e) {    
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro ao salvar set: ' . $e->getMessage();
    
            return false;       
        }
    }

    // uncheckSet, aqui vai a função para desmarcar check e atualizar o xp.
    public function UncheckSet($parms, $dados, $response, $token) 
    {
        try {
            $company_id = $token->company->id;
            $user_id = $token->uid;         
            $exercise_id = $dados->data['ex_id'];
            $serie = $dados->data['serie'];
            // aqui precisa verificar se id_exercise e serie existe, se existir, atualiza.
            $sqlvrf = $this->conexao->prepare("SELECT tw.id_exercise, te.id_exercise AS id_ex_gym FROM tb_app_training_workout tw
                                                    INNER JOIN tb_app_training_exercises te ON te.id = tw.id_exercise
                                                    INNER JOIN tb_app_training_header hd on hd.id = te.id_header
                                                    WHERE hd.id_member = :uid AND tw.id_exercise = :exercise_id AND tw.serie = :serie AND hd.id_company = :company_id AND hd.final_date IS NULL and tw.checked = 1 LIMIT 1");   
            $sqlvrf->execute(['uid' => $user_id, 'exercise_id' => $exercise_id, 'company_id' => $company_id, 'serie' => $serie]);
            $vrfset = $sqlvrf->fetch(\PDO::FETCH_ASSOC);    

            if($vrfset)
            {
                $sqlupdate = $this->conexao->prepare("UPDATE tb_app_training_workout SET checked = 0 WHERE id_exercise = :id and serie = :serie");
                $sqlupdate->execute(['id' => $vrfset['id_exercise'], 'serie' => $serie]);
            } else {
                $response->status = 'error';
                $response->code_error = 404;
                $response->message = 'Série não encontrada ou não disponível para atualização';
            }

            // aqui precisa recalcular o XP do treino usando a função calcularXP.
            $sqlheader = $this->conexao->prepare("SELECT id_header FROM tb_app_training_exercises WHERE id = :id_exercise");
            $sqlheader->execute(['id_exercise' => $exercise_id]);
            $header = $sqlheader->fetch(\PDO::FETCH_ASSOC);     

            if($header) {
                $xp = $this->calcularXP($header['id_header'], $company_id);
                $sqlupdate = $this->conexao->prepare("UPDATE tb_app_training_header SET xp = :xp WHERE id = :id");
                $sqlupdate->execute(['xp' => $xp, 'id' => $header['id_header']]);
            }   

            // aqui monta a resposta com ['success' => true, 'novo_xp' => $storage['usuario_xp']]
            $response->status = 'success';
            $response->code_error = 200;        
            $response->message = 'Set desmarcado com sucesso';
            $response->data = [
                    'success' => true,
                    'novo_xp' => $xp
                ];

            return true;
        } catch (\PDOException $e) {
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro ao desmarcar set: ' . $e->getMessage();
    
            return false;       
        }
    }

    // aqui vai a função para excluir serie. Caso o usuário exclua uma serie, as series seguintes precisam ser renumeradas.
    public function DeleteSet($parms, $dados, $response, $token) 
    {
        try {
            $company_id = $token->company->id;
            $user_id = $token->uid;
            $exercise_id = $dados->data['ex_id'];
            $serie = $dados->data['serie'];
            // aqui precisa verificar se id_exercise e serie existe, se existir, deleta.
            $sqlvrf = $this->conexao->prepare("SELECT tw.id_exercise, te.id_exercise AS id_ex_gym FROM tb_app_training_workout tw
                                                    INNER JOIN tb_app_training_exercises te ON te.id = tw.id_exercise
                                                    INNER JOIN tb_app_training_header hd on hd.id = te.id_header
                                                    WHERE hd.id_member = :uid AND tw.id_exercise = :exercise_id AND tw.serie = :serie AND hd.id_company = :company_id AND hd.final_date IS NULL LIMIT 1");      
            $sqlvrf->execute(['uid' => $user_id, 'exercise_id' => $exercise_id, 'company_id' => $company_id, 'serie' => $serie]);
            $vrfset = $sqlvrf->fetch(\PDO::FETCH_ASSOC);

            if($vrfset)
            {
                $sqldelete = $this->conexao->prepare("DELETE FROM tb_app_training_workout WHERE id_exercise = :id and serie = :serie");
                $sqldelete->execute(['id' => $vrfset['id_exercise'], 'serie' => $serie]);

                // aqui precisa renumerar as series seguintes, ou seja, diminuir 1 da serie das series seguintes.
                $sqlupdate = $this->conexao->prepare("UPDATE tb_app_training_workout SET serie = serie - 1 WHERE id_exercise = :id and serie > :serie");
                $sqlupdate->execute(['id' => $vrfset['id_exercise'], 'serie' => $serie]);
            } else {
                $response->status = 'error';
                $response->code_error = 404;
                $response->message = 'Série não encontrada ou não disponível para exclusão';
            }

             // aqui precisa recalcular o XP do treino usando a função calcularXP.
             $sqlheader = $this->conexao->prepare("SELECT id_header FROM tb_app_training_exercises WHERE id = :id_exercise");
             $sqlheader->execute(['id_exercise' => $exercise_id]);
             $header = $sqlheader->fetch(\PDO::FETCH_ASSOC);     

             if($header) {
                 $xp = $this->calcularXP($header['id_header'], $company_id);
                 $sqlupdate = $this->conexao->prepare("UPDATE tb_app_training_header SET xp = :xp WHERE id = :id");
                 $sqlupdate->execute(['xp' => $xp, 'id' => $header['id_header']]);
             }  

            // aqui monta a resposta com ['success' => true, 'novo_xp' => $storage['usuario_xp']]
            $response->status = 'success';
            $response->code_error = 200;
            $response->message = 'Série excluída com sucesso';
            $response->data = [
                    'success' => true,
                    'novo_xp' => $xp
                ];

            return true;
        } catch (\PDOException $e) {
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro ao excluir série: ' . $e->getMessage();
    
            return false;       
        }
    }

    // aqui vai a função para finalizar o treino. Precisa atualizar a data de finalização do treino e calcular o XP total do treino usando a função calcularXP e precisa também retornar nome_treino, usuario, tempo (HH:MM),tonelagem, minutos e xp+final, dentro de um array chamado resumo.
    public function FinishWorkout($parms, $dados, $response, $token) 
    {
        try {
            $company_id = $token->company->id;
            $user_id = $token->uid;
            $treino_id = $parms[0];

            // aqui precisa buscar o treino do usuario para pegar a data de inicio e calcular o tempo de treino.
            $sqlvrf = $this->conexao->prepare("SELECT * FROM tb_app_training_header WHERE id_member = :uid AND id_training = :treino_id AND final_date is null AND id_company = :company_id LIMIT 1");
            $sqlvrf->execute(['uid' => $user_id, 'treino_id' => $treino_id, 'company_id' => $company_id]);
            $vrftreino = $sqlvrf->fetch(\PDO::FETCH_ASSOC);

            if($vrftreino)
            {
                $initial_date = new \DateTime($vrftreino['initial_date']);
                $final_date = new \DateTime();
                $interval = $initial_date->diff($final_date);                
                $horas = (int)$interval->format('%H');
                $minutos = (int)$interval->format('%I');

                if ($horas > 0) {
                    $tempo_treino = $minutos > 0 ? "{$horas}h {$minutos}m" : "{$horas} h";
                } else {
                    $tempo_treino = "{$minutos} min";
                }

                /* se existe checked igual a 0 e peso e reps diferente de null, precisa passar tudo para 1
                aqui precisa listar todos os workouts com peso e reps null e se existir, excluir, usando id_exercises e serie como chave */
                $this->SanitizaWorkout($user_id, $vrftreino['id'], $company_id);
                
                //verifica a tonelagem e tempo do treino usando a função calcularTonelagem.
                $tonelagem = $this->calcularTonelagem($user_id, $treino_id, $company_id, $vrftreino['id']);

                // aqui pega o XP total do treino usando a função calcularXP.
                $xp_total = $this->calcularXP($vrftreino['id'], $company_id);

                // se xp, tonelagem e tempo forem 0 ou null, exclui o header, caso contrário, atualiza o header com a data de finalização, xp, tonelagem e tempo.
                if ($xp_total == 0 && ($tonelagem['tonelagem'] ?? 0) == 0 && ($tonelagem['tempo'] ?? 0) == 0) {
                    $sqldelete = $this->conexao->prepare("DELETE FROM tb_app_training_header WHERE id = :id");
                    $sqldelete->execute(['id' => $vrftreino['id']]);

                    $response->status = 'success';
                    $response->code_error = 200;
                    $response->message = 'Treino finalizado com sucesso';
                    $response->data = [
                            'success' => true,
                            'resumo' => [
                                'nome_treino' => $vrftreino['name_training'],
                                'usuario' => $token->social_name ?? explode(' ', $token->name)[0],
                                'tempo' => $tempo_treino,
                                'tonelagem' => 0,
                                'minutos' => 0,
                                'xp_final' => 0
                            ]
                        ];

                    return true;
                }
                   
                // aqui atualiza a data de finalização do treino e o XP total do treino.
                $sqlupdate = $this->conexao->prepare("UPDATE tb_app_training_header SET final_date = NOW(), xp = :xp, total_load = :total_load, total_minutes = :total_minutes WHERE id = :id");
                $sqlupdate->execute(['xp' => $xp_total, 'total_load' => $tonelagem['tonelagem'] ?? 0, 'total_minutes' => $tonelagem['tempo'] ?? 0, 'id' => $vrftreino['id']]); 

                // se xp for diferente de 0, adiciona saldo.
                if ($xp_total > 0) {
                    $this->adicionarXPBalance($user_id, $company_id, $xp_total);
                }

                // aqui monta a resposta com o resumo do treino.
                $response->status = 'success';
                $response->code_error = 200;
                $response->message = 'Treino finalizado com sucesso';
                $response->data = [
                        'success' => true,
                        'resumo' => [
                            'nome_treino' => $vrftreino['name_training'],
                            'usuario' => $token->social_name ?? explode(' ', $token->name)[0],
                            'tempo' => $tempo_treino,
                            'tonelagem' => rtrim(rtrim(number_format($tonelagem['tonelagem'] ?? 0, 2, '.', ''), '0'), '.'),
                            'minutos' => rtrim(rtrim(number_format($tonelagem['tempo'] ?? 0, 2, '.', ''), '0'), '.'),
                            'xp_final' => $xp_total
                        ]
                    ];

                return true;
            } else {
                $response->status = 'error';
                $response->code_error = 404;
                $response->message = 'Treino não encontrado ou já finalizado '.$treino_id;
                return false;
            }   
        } catch (\PDOException $e) {
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro ao finalizar treino: ' . $e->getMessage();
            return false;
        }
    }
}