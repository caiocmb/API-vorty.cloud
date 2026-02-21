<?php
//paginas a incluir
include(__DIR__.'/../vendor/autoload.php');

//carrega as variaveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__FILE__,2));
$dotenv->load();

//debug, caso seja preciso habilitar em producao
if($_ENV['DISPLAY_ERRORS'] == "true")
{   
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

//set timezone and locale padrão
date_default_timezone_set("America/Sao_Paulo");
setlocale(LC_ALL, 'pt_BR');

//CORS
//especificação dos domínios dos quais as solicitações são permitidas
header('Access-Control-Allow-Origin: *');

//especificação de quais métodos de solicitação são permitidos
header('Access-Control-Allow-Methods: PUT, GET, POST, DELETE');

//cabeçalhos adicionais que podem ser enviados junto com a solicitação CORS
header('Access-Control-Allow-Headers: X-Requested-With,Authorization,Content-Type');

//define a idade para 1 dia para melhorar a velocidade/cache.
//header('Access-Control-Max-Age: 86400');

//filtra as rotas e joga para array
$url_atual = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
$rotas = array_values(array_filter(explode('/',$url_atual)));

//verifica se existe array das rotas, caso seja sem rota
if(!isset($rotas[0]))
{    
    include(__DIR__.'/../StaticPages/home_api.php'); 
    die();
}

//se existir cria o caminho para o arquivo da rota
$rota = __DIR__.'/../Src/Routes/' . $rotas[0] . '.php';

//valida se a rota existe, caso não, retorna Not found
if(!file_exists($rota)) 
{
    $return = [
        "status" => 'error',
        "message" => 'Route not found'
    ];

    header('Content-Type: application/json; charset=utf-8');
    header("HTTP/1.1 403 Not Found");
    echo json_encode($return);
    die();
}

//caso esteja tudo certo, inclui a rota e a tratativa continua pelas rotas
include($rota);