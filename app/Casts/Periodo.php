<?php

namespace App\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Periodo implements CastsAttributes
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
        if ($value == null) return '';
        $valor = $value;
        $ano = substr($value, 0, 4);
        $mes = substr($value, -2);

        $valor = "$mes/$ano";
        return $valor;
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
        if ($value == null || $value == '/') return '';
        if (strpos($value, '/')) {
            $periodo = explode('/', $value);
            return "$periodo[1]$periodo[0]";
        } else {
            $valor = new Carbon($value);
            return $valor->format("Ym");
        }
    }
}
