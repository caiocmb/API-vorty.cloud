<?php

namespace Src\Models;

use Src\Models\ConnectDB;
use Firebase\JWT\JWT;
use Src\Services\LogServices;

class MonthlyfeeModel extends ConnectDB 
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
            $sql = $this->conexao->prepare("
            
            SELECT 
                CASE 
                    WHEN m.status = 'V' THEN 'Vencido' 
                    WHEN m.status = 'P' THEN 'Pago' 
                    WHEN m.status = 'A' THEN 'Aguardando' 
                    WHEN m.status = 'F' THEN 'Valores Pendentes' 
                    WHEN m.status = 'C' THEN 'Cancelado'  
                END as status,
                CASE 
                    WHEN m.status = 'V' THEN 'bg-danger text-white' 
                    WHEN m.status = 'P' THEN 'bg-success-lt text-success' 
                    WHEN m.status = 'A' THEN 'bg-blue-lt text-blue' 
                    WHEN m.status = 'F' THEN 'bg-yellow-lt text-yellow' 
                    WHEN m.status = 'C' THEN 'bg-secondary-lt text-secondary'  
                END as color,
                m.value,
                date_format(m.created_at,'%M/%Y') as month,
                CASE 
                    WHEN m.status = 'V' THEN concat('Vencida há ',DATEDIFF(now(), m.due_date), 'dias') 
                    WHEN m.status = 'P' THEN concat('Pago em ',date_format(m.payment_date,'%d/%m')) 
                    WHEN m.status = 'A' THEN concat('Vence em ',date_format(m.due_date,'%d/%m')) 
                    WHEN m.status = 'F' THEN 'Valores Pendentes' 
                    WHEN m.status = 'C' THEN 'Cobrança estornada'  
                END as description
            FROM tb_payment m
			LEFT JOIN tb_gym_member_plan g on g.id_member = m.id_member and g.id_company = m.id_company and g.status = 'A'
			LEFT JOIN tb_plans p on p.id = g.id_plan and m.id_company = g.id_company
            WHERE 
                m.id_company = :company_id 
                AND m.id_member = :userid 
            ORDER BY m.created_at DESC
            LIMIT 12");
    
            $sql->execute([
                'company_id' => $data_received->company->id,
                'userid' => $data_received->uid
            ]);
    
            $userFound = $sql->fetchAll(\PDO::FETCH_ASSOC);
    
            if (!$userFound) {
                $response->status = 'error';
                $response->code_error = 404;
                $response->message = 'Nenhuma mensalidade encontrada';
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
    
}