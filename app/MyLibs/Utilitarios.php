<?php

namespace App\MyLibs;

class Utilitarios
{
    public static function removerCaracteresEspeciaisCpf($cpf)
    {
        $caracteresRemocao = ["/[^0-9]/"];
        return preg_replace($caracteresRemocao, "", $cpf);
    }

    public static function conveterPeriodo($valor)
    {
        $periodo = explode('/', $valor);
        $inicio =  "$periodo[1]$periodo[0]";
        return $inicio;
    }
}
