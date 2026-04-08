<?php

namespace Src\Controllers;

use Src\Models\WorkoutModel;

class WorkoutController
{
    private $model,$error_acu;


    public function __construct()
    {
        $this->model = new WorkoutModel();
    }

    private function validaId($id, $response) 
    {
        $valor = isset($id) ? urldecode($id) : null;

        if (
            empty($valor) ||
            !preg_match('/^[A-Za-z0-9\/+=]+$/', $valor) || // valida caracteres base64
            ($decoded = base64_decode($valor, true)) === false ||
            !ctype_digit($decoded) ||
            (int)$decoded <= 0
        ) {
        
            $response->status = 'error';
            $response->code_error = 400;
            $response->message = 'Parâmetro inválido';

            return false;
            die;
        }

        $parm = [(int)$decoded];

        return $parm;
    }

    //recebe os dados e faz o que foi solicitado
    public function Send($response,$dados,$token) 
    {   global $rotas;
        $dados = (object)$dados;
        $token = (object)$token;

        if($dados->method == 'GET')
        {            
            $parm = [];

            // puxa o treino ativo do usuario
            if(isset($rotas[1]) && $rotas[1] == 'active_workout')
            {          
  
                $parm = $this->validaId($rotas[2], $response);

                return $this->model->Workout($parm,$response,$token);
                die();
            }
            
            // puxa o historico de exercicios do usuario
            if(isset($rotas[1]) && $rotas[1] == 'exercise_history')
            {
    
                $parm = $this->validaId($rotas[2], $response);

                return $this->model->ListarHistoricoExercicios($parm, $dados, $response, $token);
                die();
            }

            // lista o que foi feito hoje em sets
            if(isset($rotas[1]) && $rotas[1] == 'today_sets')
            {
                $parm = $this->validaId($rotas[2], $response);

                return $this->model->ListarTodaySets($parm, $dados, $response, $token);
                die();

            }         
            
        }

        if($dados->method == 'POST')
        {
            // adiciona as series conforme vai criando
            if(isset($rotas[1]) && $rotas[1] == 'add_set')
            {
                $parm = $this->validaId($rotas[2], $response);

                return $this->model->AddSet($parm, $dados, $response, $token);
                die();

            }

            // finaliza o treino do dia, marcando a data de finalização no header do treino. Rota finish_workout
            if(isset($rotas[1]) && $rotas[1] == 'finish_workout')
            {
                $parm = $this->validaId($rotas[2], $response);

                return $this->model->FinishWorkout($parm, $dados, $response, $token);
                die();

            }

        }   

        if($dados->method == 'PUT')
        {
            // salva os sets feitos hoje 
            if(isset($rotas[1]) && $rotas[1] == 'save_set')
            {
                $parm = $this->validaId($rotas[2], $response);

                return $this->model->SalvarTodaySets($parm, $dados, $response, $token);
                die();

            }

            // uncheck_set, para desmarcar um set marcado como feito, caso o usuario queira corrigir algo.
            if(isset($rotas[1]) && $rotas[1] == 'uncheck_set')
            {
                $parm = $this->validaId($rotas[2], $response);

                return $this->model->UncheckSet($parm, $dados, $response, $token);
                die();

            } 
        }

        if($dados->method == 'DELETE')
        {
            // deletar as series criadas.
            if(isset($rotas[1]) && $rotas[1] == 'delete_set')
            {
                $parm = $this->validaId($rotas[2], $response);

                return $this->model->DeleteSet($parm, $dados, $response, $token);
                die();

            }
        }

       
        // se não encontra nada, retorna erro
        $response->status = 'error';
        $response->code_error = 400;
        $response->message = 'Metodo não permitido ou inexistente';
    
    }
}