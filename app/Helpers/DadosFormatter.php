<?php

namespace App\Helpers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DadosFormatter
{
    public static function formatarData($data)
    {
        if (empty($data)) {
            return null;
        }
        return Carbon::parse($data)->format('d/m/Y');
    }

    public static function formatarCpf($cpf)
    {
        if (empty($cpf)) {
            return null;
        }
        $cpf = preg_replace('/\D/', '', $cpf);
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }

    public static function formatarCelular($celular)
    {
        if (empty($celular)) {
            return null;
        }
        $cel = preg_replace('/\D/', '', $celular);
        if (strlen($cel) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $cel);
        }
        return $celular;
    }

    public static function toUpper($texto)
    {
        if (empty($texto)) {
            return null;
        }
        return Str::upper($texto);
    }
}
