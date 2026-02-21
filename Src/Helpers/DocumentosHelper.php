<?php 
namespace Src\Helpers;

class DocumentosHelper {

    // validar cpf
    public static function validarCpf($cpf) {
        // 1. Remove caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', (string)$cpf);

        // 2. Verifica se tem 11 dígitos
        if (strlen($cpf) != 11) {
            return false;
        }

        // 3. Verifica se foi informada uma sequência de dígitos repetidos (Ex: 111.111.111-11)
        // Isso é necessário porque o cálculo matemático aceita sequências repetidas, mas elas são inválidas.
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // 4. Cálculo matemático dos dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }

    //validar cnpj
    public static function validarCnpj($cnpj) {
        // 1. Remove caracteres não numéricos
        $cnpj = preg_replace('/[^0-9]/', '', (string)$cnpj);

        // 2. Verifica se tem 14 dígitos
        if (strlen($cnpj) != 14) {
            return false;
        }

        // 3. Verifica sequências repetidas (ex: 00.000.000/0000-00)
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        // 4. Cálculo dos dígitos verificadores
        for ($t = 12; $t < 14; $t++) {
            $d = 0;
            $c = ($t - 7); // Peso inicial
            
            for ($i = 0; $i < $t; $i++) {
                $d += $cnpj[$i] * $c;
                $c = ($c == 2) ? 9 : --$c; // Se o peso chegar em 2, volta para 9
            }
            
            $d = ((10 * $d) % 11) % 10;
            
            if ($cnpj[$i] != $d) {
                return false;
            }
        }

        return true;
    }
}