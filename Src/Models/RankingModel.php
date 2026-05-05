<?php

namespace Src\Models;

use Src\Models\ConnectDB;
use Firebase\JWT\JWT;
use Src\Services\LogServices;

class RankingModel extends ConnectDB 
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

    // na função abaixo, vou consultar a tabela tb_app_xp_balance para retornar o ranking por saldo de XP, apenas os 10 primeiros. Vou trazer o apelido, se houver, se não, apenas o primeiro nome. Caso haja empate de saldo, a diferença será tirada pela quantidade de treinos realizada nos ultimos 30 dias. Num segundo bloco de retorno, vou trazer a posição global do usuario, quandos XPs ele tem e quanto falta para a proxima posição, validando se ele não é o primeiro da lista, dai tras null para quanto falta 
    public function Ranking($parms, $response, $data_received) : string | bool
    {
        try {

            $company_id = $data_received->company->id;
            $userid = $data_received->uid;

            $sql = $this->conexao->prepare("SELECT 
                                                ROW_NUMBER() OVER (
                                                    ORDER BY b.balance DESC, 
                                                    (SELECT COUNT(*) FROM tb_app_training_header h WHERE h.id_member = u.id AND h.final_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) DESC
                                                ) AS posicao,
                                                u.foto_app as foto,
                                                COALESCE(u.social_name, SUBSTRING_INDEX(u.name, ' ', 1)) as nickname,
                                                b.balance as xp_total,
                                                (SELECT COUNT(*) FROM tb_app_training_header h WHERE h.id_member = u.id AND h.final_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as treinos_ultimos_30_dias
                                            FROM tb_gym_member u
                                            INNER JOIN tb_app_xp_balance b ON b.member_id = u.id AND b.id_company = :company_id
                                            WHERE b.id_company = :company_id and u.status not in ('I')
                                            ORDER BY xp_total DESC, treinos_ultimos_30_dias DESC
                                            LIMIT 10");
            $sql->execute(['company_id' => $company_id]);
            $ranking = $sql->fetchAll(\PDO::FETCH_ASSOC);

            // aqui verifico se tem foto, se não tiver, coloco a foto default "no_picture.webp"
            foreach ($ranking as &$member) {
                if (empty($member['foto'])) {
                    $member['foto'] = 'no_picture.webp';
                }
            }

            // aqui vou calcular a posição global do usuário e quanto falta para a próxima posição. Tem que levar em conta que pode haver mesmo XP de varios usuarios e que o desempate deve ser feito igual a consulta anterior, pela quantidade de treinos.
            $sql = $this->conexao->prepare("WITH RankingGeral AS (
                                                -- Passo 1: Calculamos os pontos e o ranking de todos os membros
                                                SELECT 
                                                    u.id as user_id,
                                                    COALESCE(u.social_name, SUBSTRING_INDEX(u.name, ' ', 1)) as nickname,
                                                    b.balance as xp_total,
                                                    (SELECT COUNT(*) 
                                                    FROM tb_app_training_header h 
                                                    WHERE h.id_member = u.id 
                                                    AND h.final_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as treinos_ultimos_30_dias,
                                                    -- Geramos a posição numérica baseada no seu critério
                                                    ROW_NUMBER() OVER (
                                                        ORDER BY b.balance DESC, 
                                                        (SELECT COUNT(*) FROM tb_app_training_header h WHERE h.id_member = u.id AND h.final_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) DESC
                                                    ) as posicao_atual
                                                FROM tb_gym_member u
                                                INNER JOIN tb_app_xp_balance b ON b.member_id = u.id
                                                WHERE b.id_company = :company_id and u.status not in ('I')
                                            ),
                                            ComparativoSoberano AS (
                                                -- Passo 2: Usamos LAG para capturar os dados de quem está na posição acima (posicao_atual - 1)
                                                SELECT 
                                                    *,
                                                    LAG(xp_total) OVER (ORDER BY posicao_atual) as xp_superior,
                                                    LAG(treinos_ultimos_30_dias) OVER (ORDER BY posicao_atual) as treinos_superior
                                                FROM RankingGeral
                                            )
                                            -- Passo 3: Filtramos apenas o usuário desejado e calculamos a diferença
                                            SELECT 
                                                user_id,
                                                nickname,
                                                xp_total as meu_xp,
                                                posicao_atual,
                                                CASE 
                                                    WHEN posicao_atual = 1 THEN NULL 
                                                    ELSE posicao_atual - 1 
                                                END as proxima_posicao,
                                                CASE 
                                                    WHEN posicao_atual = 1 THEN 0
                                                    -- Se o XP do cara de cima é maior, a diferença + 1 garante que eu passe ele
                                                    WHEN xp_superior > xp_total THEN (xp_superior - xp_total) + 1
                                                    -- Se o XP é igual, mas ele ganha nos treinos, +1 XP me coloca na frente
                                                    WHEN xp_superior = xp_total AND treinos_superior >= treinos_ultimos_30_dias THEN 1
                                                    ELSE 0 
                                                END as xp_para_alcancar,
                                                -- CÁLCULO DO PERCENTUAL
                                                CASE 
                                                    WHEN posicao_atual = 1 THEN 100
                                                    WHEN xp_superior = 0 THEN 100
                                                    -- Calculamos quanto o meu XP representa em relação ao XP de quem está acima
                                                    ELSE LEAST(ROUND((xp_total / xp_superior) * 100, 2), 99.99)
                                                END as percentual_progresso
                                            FROM ComparativoSoberano
                                            WHERE user_id = :user_id;");
            $sql->execute(['company_id' => $company_id, 'user_id' => $userid]);
            $posicao_usuario = $sql->fetch(\PDO::FETCH_ASSOC);

            // aqui quero trazer o ranking entre amigos, só vai trazer se existir na tabela SELECT `id_company`, `member_uid_1`, `member_uid_2`, `connected_at` FROM `tb_app_member_connections`. Ela deve vincular a tabela de balance para ver a posição entre os amigos
            $sql = $this->conexao->prepare("SELECT * FROM (
                                                -- Parte 1: Busca os Amigos do usuário
                                                SELECT 
                                                    u.id AS friend_uid,
                                                    COALESCE(u.social_name, SUBSTRING_INDEX(u.name, ' ', 1)) AS nickname,
                                                    u.foto_app as foto,
                                                    COALESCE(b.balance, 0) AS xp_total, -- Se for NULL, retorna 0
                                                    (SELECT COUNT(*) 
                                                    FROM tb_app_training_header h 
                                                    WHERE h.id_member = u.id 
                                                    AND h.final_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS treinos_ultimos_30_dias
                                                FROM tb_app_member_connections c
                                                INNER JOIN tb_gym_member u ON (
                                                    (u.id = c.member_uid_1 AND c.member_uid_2 = :user_id) 
                                                    OR 
                                                    (u.id = c.member_uid_2 AND c.member_uid_1 = :user_id)
                                                )
                                                -- Mudança para LEFT JOIN para não excluir usuários sem saldo
                                                LEFT JOIN tb_app_xp_balance b ON b.member_id = u.id AND b.id_company = :company_id
                                                WHERE c.id_company = :company_id
                                                AND (c.member_uid_1 = :user_id OR c.member_uid_2 = :user_id)

                                                UNION ALL

                                                -- Parte 2: Busca os dados do próprio usuário
                                                SELECT 
                                                    u.id AS friend_uid,
                                                    'Você' AS nickname,
                                                    u.foto_app as foto,
                                                    COALESCE(b.balance, 0) AS xp_total, -- Se for NULL, retorna 0
                                                    (SELECT COUNT(*) 
                                                    FROM tb_app_training_header h 
                                                    WHERE h.id_member = u.id 
                                                    AND h.final_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS treinos_ultimos_30_dias
                                                FROM tb_gym_member u
                                                -- Mudança para LEFT JOIN aqui também
                                                LEFT JOIN tb_app_xp_balance b ON b.member_id = u.id AND b.id_company = :company_id
                                                WHERE u.id = :user_id
                                                AND EXISTS (
                                                    SELECT 1 FROM tb_app_member_connections 
                                                    WHERE id_company = :company_id 
                                                    AND (member_uid_1 = :user_id OR member_uid_2 = :user_id)
                                                )
                                            ) AS ranking_social
                                            ORDER BY xp_total DESC, treinos_ultimos_30_dias DESC;");

            $sql->execute(['company_id' => $company_id, 'user_id' => $userid]);
            $ranking_amigos = $sql->fetchAll(\PDO::FETCH_ASSOC);

             // aqui verifico se tem foto, se não tiver, coloco a foto default "no_picture.webp"
            foreach ($ranking_amigos as &$membera) {
                if (empty($membera['foto'])) {
                    $membera['foto'] = 'no_picture.webp';
                }
            }

            // Definição dos grupos de frases
            $pool_frases = [
                'primeiro' => [
                    "Nível insano! 🚀",
                    "Pode pedir música! 🎶",
                    "Imbatível no topo. 🔥",
                    "O exemplo do grupo! 🏆",
                    "Simplesmente elite. ✨"
                ],
                'segundo' => [
                    "No rastro da liderança! 👀",
                    "Só mais um scoop... 🥤",
                    "Pressão total no topo! 💪",
                    "Sentindo o cheirinho do ouro?",
                    "A liderança que se cuide! 🏃"
                ],
                'terceiro' => [
                    "Pódio garantido! 🥉",
                    "Ninguém tira do Top 3.",
                    "Olhando pra cima! 👀",
                    "Presença confirmada no pódio.",
                    "Medalha de respeito! ✨"
                ],
                'demais' => [
                    "Cadê o cardio de hoje? 🏃",
                    "Hoje teve treino ou só resenha? 🗣️",
                    "Evolução constante. 📈",
                    "Foco no progresso!",
                    "Vem treinar, sumiço!",
                    "Segunda-feira você recupera! 😂",
                    "Subindo no sapatinho...",
                    "A meta é não ser o último lugar!"
                ]
            ];

            // Aplicando as frases ao array de ranking
            foreach ($ranking_amigos as $index => &$membera) {
                $posicao = $index + 1; // Array começa em 0, ranking em 1
                
                // Identifica qual categoria de frase usar
                if ($posicao == 1) {
                    $categoria = 'primeiro';
                } elseif ($posicao == 2) {
                    $categoria = 'segundo';
                } elseif ($posicao == 3) {
                    $categoria = 'terceiro';
                } else {
                    $categoria = 'demais';
                }

                // Seleciona uma frase aleatória dentro da categoria
                // Usamos o ID do usuário como semente para a frase não mudar toda hora no mesmo dia
                // Se quiser que mude a cada refresh total, use apenas array_rand()
                $random_key = array_rand($pool_frases[$categoria]);
                $membera['status_frase'] = $pool_frases[$categoria][$random_key];
                
                // Adiciona a posição ao array para facilitar no HTML
                $membera['posicao'] = $posicao;
            }

            $response->status = 'success';
            $response->message = 'Ranking encontrado com sucesso.';
            $response->data = [
                'ranking' => $ranking,
                'ranking_amigos' => $ranking_amigos,
                'posicao_usuario' => $posicao_usuario['posicao_atual'] ?? null,
                'xp_total_usuario' => $posicao_usuario['meu_xp'] ?? 0,
                'proxima_posicao' => $posicao_usuario['proxima_posicao'] ?? null,
                'proxima_posicao_xp_faltando' => $posicao_usuario['xp_para_alcancar'] ?? null,
                'percentual_progresso' => $posicao_usuario['percentual_progresso'] ?? null
            ];
            return true;

        } catch (\PDOException $e) {
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro ao executar a consulta no banco de dados: ' . $e->getMessage();
            return false;
        }
    }

    // aqui vou criar uma função para trazer quem se conectou com a pessoa nos ultimos 15 segundos, de acordo com a query SELECT `id_company`, `member_uid_1`, `member_uid_2`, `connected_at` FROM `tb_app_member_connections`. O Uid pode estar em qualwur uma das colunas, então deve verificar quem se conectou nos ultimos 15 segundos somente para o app validar.
    public function CheckConnectionFriend($parms,$response,$data_received) : string | bool 
    {
        $company_id = $data_received->company->id;
        $userid = $data_received->uid;

        try {
            $sql = $this->conexao->prepare("SELECT 
                                                CASE 
                                                    WHEN member_uid_1 = :user_id THEN member_uid_2 
                                                    ELSE member_uid_1 
                                                END AS friend_uid
                                            FROM tb_app_member_connections
                                            WHERE id_company = :company_id
                                            AND (member_uid_1 = :user_id OR member_uid_2 = :user_id)
                                            AND connected_at >= DATE_SUB(NOW(), INTERVAL 15 SECOND);");
            $sql->execute(['company_id' => $company_id, 'user_id' => $userid]);
            $connections = $sql->fetchAll(\PDO::FETCH_ASSOC);

            if ($connections) {
                $response->status = 'success';
                $response->message = 'Conexões recentes encontradas.';
                $response->data = $connections;
            } else {
                $response->status = 'error';
                $response->code_error = 404;
                $response->message = 'Nenhuma conexão recente encontrada.';
            }
            return true;

        } catch (\PDOException $e) {
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro ao executar a consulta no banco de dados: ' . $e->getMessage();
            return false;
        }
    }

    // aqui vamos criar a função pra receber o uuid do usuario que vamos nos conectar. ele deve inserir na tabela usando sempre o id em ordem do array asc no campo uid 1 e depois no 2. O cmapo que vamos receber é codigo
    public function ConnectUser($parms, $dados, $response, $token)  : bool
    {
        $company_id = $token->company->id;
        $userid = $token->uid;
        $codigo_conexao = $dados->data['codigo'];

        try {
            // Primeiro, verificamos se o código de conexão é válido e obtemos o ID do usuário correspondente
            $sql = $this->conexao->prepare("SELECT id FROM tb_gym_member WHERE id_company = :company_id AND id = :codigo_conexao");
            $sql->execute(['company_id' => $company_id, 'codigo_conexao' => $codigo_conexao]);
            $friend = $sql->fetch(\PDO::FETCH_ASSOC);

            if (!$friend) {
                $response->status = 'error';
                $response->code_error = 404;
                $response->message = 'Código de conexão inválido.';
                return false;
            }

            $friend_uid = $friend['id'];

            // Verificamos se já existe uma conexão entre os dois usuários
            $sql = $this->conexao->prepare("SELECT * FROM tb_app_member_connections WHERE id_company = :company_id AND ((member_uid_1 = :user_id AND member_uid_2 = :friend_uid) OR (member_uid_1 = :friend_uid AND member_uid_2 = :user_id))");
            $sql->execute(['company_id' => $company_id, 'user_id' => $userid, 'friend_uid' => $friend_uid]);
            if ($sql->fetch(\PDO::FETCH_ASSOC)) {
                $response->status = 'error';
                $response->code_error = 409;
                $response->message = 'Vocês já estão conectados.';
                return false;
            }

            // Inserimos a nova conexão
            $sql = $this->conexao->prepare("INSERT INTO tb_app_member_connections (id_company, member_uid_1, member_uid_2, connected_at) VALUES (:company_id, :user_id, :friend_uid, NOW())");
            $sql->execute(['company_id' => $company_id, 'user_id' => min($userid, $friend_uid), 'friend_uid' => max($userid, $friend_uid)]);

            if ($sql->rowCount() > 0) {
                $response->status = 'success';
                $response->message = 'Conexão estabelecida com sucesso.';
                return true;
            } else {
                $response->status = 'error';
                $response->code_error = 500;
                $response->message = 'Erro ao estabelecer a conexão.';
                return false;
            }
        } catch (\PDOException $e) {
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro ao executar a consulta no banco de dados: ' . $e->getMessage();
            return false;
        }
            
    }
    
}