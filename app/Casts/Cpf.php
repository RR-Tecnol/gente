<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Cpf implements CastsAttributes
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
        return $value;
        if ($value === null) {
            return '';
        }

        // Adiciona zeros à esquerda para garantir que a string tenha 11 caracteres
        $cpf = str_pad($value, 11, '0', STR_PAD_LEFT);

        $parte_um     = substr($cpf, 0, 3);
        $parte_dois   = substr($cpf, 3, 3);
        $parte_tres   = substr($cpf, 6, 3);
        $parte_quatro = substr($cpf, 9, 2);

        $cpf_formatado = "$parte_um.$parte_dois.$parte_tres-$parte_quatro";

        return $cpf_formatado;
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
        if ($value == null) {
            return null;
        }

        $cpf = preg_replace('/[^0-9]/', '', $value);

        return $cpf;
    }
}
