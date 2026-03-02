<?php

namespace Src\Models;

use PDO;
use PDOException;

class ConnectDB {

    /** @var PDO */
    private static $Connect,$ConnectLog;

    // conecta na base do saas
    private static function ConectarSaas() {
        try {
            if (self::$Connect == null) {
                $dsn = 'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_DATABASE'] . ';charset=utf8mb4';
                
                // Agrupamos todos os comandos em uma única string separada por ponto e vírgula
                $commands = "SET lc_time_names = 'pt_BR'; "
                        . "SET time_zone = '-03:00'; "
                        . "SET NAMES utf8mb4; "
                        . "SET character_set_connection=utf8mb4; "
                        . "SET character_set_client=utf8mb4; "
                        . "SET character_set_results=utf8mb4;";

                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Facilita sua vida na Controller
                    PDO::MYSQL_ATTR_INIT_COMMAND => $commands
                ];

                self::$Connect = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], $options);
            }
        } catch (PDOException $e) {
            // Em produção, não use echo $e->getMessage(). Logue em arquivo.
            error_log("Erro na conexão SaaS: " . $e->getMessage());
            return null;
        }
        
        return self::$Connect;
    }

    // conecta na base de log
    private static function ConectarLog() {
        try {

            //Verifica se a conexão não existe
            if (self::$ConnectLog == null):

                $dsn = 'mysql:host=' . $_ENV['DB_HOST_LOG'] . ';dbname=' . $_ENV['DB_DATABASE_LOG'];
                self::$ConnectLog = new PDO($dsn, $_ENV['DB_USER_LOG'], $_ENV['DB_PASS_LOG'], null);
            endif;
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
       
        //Seta os atributos para que seja retornado as excessões do banco
        self::$ConnectLog->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        self::$ConnectLog->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND,"SET NAMES utf8");        
        self::$ConnectLog->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND,"SET character_set_connection=utf8");   
        self::$ConnectLog->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND,"SET character_set_client=utf8");   
        self::$ConnectLog->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND,"SET character_set_results=utf8");  
        self::$ConnectLog->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND,"SET PERSIST information_schema_stats_expiry = 0");  
       
        return  self::$ConnectLog;
    }

    // retorna a conexao do saas
    public static function ConnectSaas() {
        return  self::ConectarSaas();
    }

    // retorna a conexao do log
    public static function ConnectLog() {
        return  self::ConectarLog();
    }
    
    
}