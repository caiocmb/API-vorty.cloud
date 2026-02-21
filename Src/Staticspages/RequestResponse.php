<?php

namespace Src\Staticspages;

class RequestResponse
{
    public $data = [];
    public $method;
    public $status = '';
    public $message = '';
    public $code_error = 200;

    public function ApiRequest()
    {
        $data_method = $_SERVER['REQUEST_METHOD'];
        $data_body = json_decode(file_get_contents('php://input'), true);
        $headers = getallheaders();

        return [
            "method" => $data_method,
            "data" => $data_body,
            "header" => $headers
        ];
    }

    public function ApiResponse()
    {   
        if($this->status == 'error')
        {
            $return = [
                "status" => $this->status,
                "message" => $this->message
            ];
        }
        else
        {
            $return = [
                "status" => $this->status,
                "message" => $this->message,
                "data" => $this->data
            ];
        }
        
        header('Content-Type: application/json; charset=utf-8');
        header("HTTP/1.1 ".$this->code_error);
        echo json_encode($return);
    }

}