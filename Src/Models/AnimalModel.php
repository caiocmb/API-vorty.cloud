<?php

namespace Src\Models;

use Src\Models\ConnectDB;

class AnimalModel extends ConnectDB 
{
    private $conexao;
    public function __construct() 
    {
        $this->conexao = parent::retornarConexao();
    }

    public function Buscar($parms, $response, $user_id) : string | bool
    {
        try {
            $sql = $this->conexao->prepare("SELECT 
                                                a.cod_brinco,
                                                a.id_propriedade,
                                                p.nome as nome_propriedade,
                                                a.data_nascimento,
                                                a.peso_inicial,
                                                a.id_tipo_animal,
                                                t.descricao as desc_tipo_animal
                                            FROM db_animal a 
                                            INNER JOIN db_property p on p.id = a.id_propriedade and p.user_id = a.user_id
                                            INNER JOIN db_tipo_animal t on t.id = a.id_tipo_animal and t.user_id = a.user_id
                                            WHERE 
                                                1 = 1
                                                AND a.user_id = :userid and (
                                                    (:cod_brinco = '' OR a.cod_brinco = :cod_brinco) and 
                                                    (:id_propriedade = '' OR a.id_propriedade = :id_propriedade) and
                                                    (:propriedade = '' OR p.nome like (:propriedade))
                                                )");
    
            $sql->execute([
                'userid' => $user_id['id'],
                'cod_brinco' => $parms['cod_brinco'],
                'id_propriedade' => $parms['id_propriedade'],
                'propriedade' => $parms['propriedade']
            ]);
    
            $userFound = $sql->fetchAll(\PDO::FETCH_ASSOC);
    
            if (!$userFound) {
                $response->status = 'error';
                $response->code_error = 404;
                $response->message = 'Animal não encontrado';
                return false;
            }
    
            $response->status = 'success';
            $response->code_error = 200;
            $response->message = 'Listagem efetuada com sucesso';
            $response->data = $userFound;
            return true;
    
        } catch (\PDOException $e) {
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro ao executar a consulta no banco de dados: ' . $e->getMessage();
            return false;
        }
    }
    

    public function Cadastrar($parms,$response,$user_id) : string | bool
    {
        try {
            // Verifica se já existe cadastrado
            $checkSql = $this->conexao->prepare("SELECT cod_brinco FROM db_animal WHERE cod_brinco = :cod_brinco and id_propriedade = :id_propriedade and user_id = :userid");
            $checkSql->execute(['cod_brinco' => $parms->data['cod_brinco'],'id_propriedade' => $parms->data['id_propriedade'],'userid'=>$user_id['id']]);
            $exists = $checkSql->fetch(\PDO::FETCH_ASSOC);
        
            if ($exists) {
                $response->status = 'error';
                $response->code_error = 409; // Código HTTP para conflito
                $response->message = 'Brinco/propriedade já cadastrado.';
                return false;
            }
        
            // Faz o insert se não existir
            $sql = $this->conexao->prepare("
                INSERT INTO db_animal 
                VALUES (:cod_brinco, :id_propriedade, :data_nascimento, :peso_inicial, :id_tipo_animal, :user_id)
            ");
        
            $success = $sql->execute([
                'cod_brinco' => $parms->data['cod_brinco'],
                'id_propriedade' => $parms->data['id_propriedade'],
                'data_nascimento' => $parms->data['data_nascimento'] ?? null,
                'peso_inicial' => $parms->data['peso_inicial'] ?? null,
                'id_tipo_animal' => $parms->data['id_tipo_animal'] ?? null,
                'user_id' => $user_id['id']
            ]);
        
            if (!$success) {
                $response->status = 'error';
                $response->code_error = 500;
                $response->message = 'Erro ao cadastrar.';
                return false;
            }
        
            //$lastId = $this->conexao->lastInsertId();
        
            $response->status = 'success';
            $response->code_error = 200;
            $response->message = 'Cadastrado com sucesso.';
            $response->data = [
                'cod_brinco' => $parms->data['cod_brinco'],
                'id_propriedade' => $parms->data['id_propriedade']
            ];
        
            return true;
        
        } catch (\PDOException $e) {
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro no banco de dados: ' . $e->getMessage();
            return false;
        }
        
    }

    public function Atualizar($parms,$response,$user_id) : string | bool
    {
        try {
        
            // Atualiza os dados (exceto ID e user_id)
            $sql = $this->conexao->prepare("
                UPDATE db_animal SET 
                    data_nascimento = :data_nascimento, 
                    peso_inicial = :peso_inicial, 
                    id_tipo_animal = :id_tipo_animal
                WHERE cod_brinco = :cod_brinco and id_propriedade = :id_propriedade and user_id = :userid
            ");
        
            $success = $sql->execute([
                'data_nascimento' => $parms->data['data_nascimento'] ?? null,
                'peso_inicial' => $parms->data['peso_inicial'] ?? null,
                'id_tipo_animal' => $parms->data['id_tipo_animal'] ?? null,
                'cod_brinco' => $parms->data['cod_brinco'],
                'id_propriedade' => $parms->data['id_propriedade'],
                'userid' => $user_id['id']
            ]);
        
            if (!$success) {
                $response->status = 'error';
                $response->code_error = 500;
                $response->message = 'Erro ao atualizar.';
                return false;
            }
        
            $response->status = 'success';
            $response->code_error = 200;
            $response->message = 'Atualizado com sucesso.';
            $response->data = [
                'cod_brinco' => $parms->data['cod_brinco'],
                'id_propriedade' => $parms->data['id_propriedade']
            ];
        
            return true;
        
        } catch (\PDOException $e) {
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro no banco de dados: ' . $e->getMessage();
            return false;
        }
        
        
    }

    public function Excluir($parms,$response,$user_id) : string | bool
    {
        try {
            //$propertyId = $parms->data['id'];
        
            // Verifica se a propriedade existe antes de deletar
            $checkSql = $this->conexao->prepare("SELECT cod_brinco FROM db_animal WHERE cod_brinco = :cod_brinco and id_propriedade = :id_propriedade and user_id = :userid");
            $checkSql->execute(['cod_brinco' => $parms->data['cod_brinco'], 'id_propriedade' => $parms->data['id_propriedade'],'userid'=>$user_id['id']]);
            $property = $checkSql->fetch(\PDO::FETCH_ASSOC);
        
            if (!$property) {
                $response->status = 'error';
                $response->code_error = 404;
                $response->message = 'Cadastro não encontrado.';
                return false;
            }
        
            // Executa o delete
            $deleteSql = $this->conexao->prepare("DELETE FROM db_animal WHERE cod_brinco = :cod_brinco and id_propriedade = :id_propriedade and user_id = :userid");
            $success = $deleteSql->execute(['cod_brinco' => $parms->data['cod_brinco'], 'id_propriedade' => $parms->data['id_propriedade'],'userid'=>$user_id['id']]);
        
            if (!$success) {
                $response->status = 'error';
                $response->code_error = 500;
                $response->message = 'Erro ao excluir.';
                return false;
            }
        
            $response->status = 'success';
            $response->code_error = 200;
            $response->message = 'Excluido com sucesso.';
            $response->data = ['cod_brinco' => $parms->data['cod_brinco'], 'id_propriedade' => $parms->data['id_propriedade']];
            return true;
        
        } catch (\PDOException $e) {
            $response->status = 'error';
            $response->code_error = 500;
            $response->message = 'Erro no banco de dados: ' . $e->getMessage();
            return false;
        }              
        
    }
}