<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Cnpj implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function get($model, $key, $value, $attributes)
    {
        if ($value === null) {
            return '';
        }

        // Adiciona zeros à esquerda para garantir que a string tenha 14 caracteres
        $cnpj = str_pad($value, 14, '0', STR_PAD_LEFT);

        $parte_um     = substr($cnpj, 0, 2);
        $parte_dois   = substr($cnpj, 2, 3);
        $parte_tres   = substr($cnpj, 5, 3);
        $parte_quatro = substr($cnpj, 8, 4);
        $parte_cinco  = substr($cnpj, 12, 2);

        $cnpj_formatado = "$parte_um.$parte_dois.$parte_tres/$parte_quatro-$parte_cinco";

        return $cnpj_formatado;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function set($model, $key, $value, $attributes)
    {
        if ($value === null) {
            return null;
        }

        // Remove tudo que não for número
        $cnpj = preg_replace('/[^0-9]/', '', $value);

        return $cnpj;
    }
}
