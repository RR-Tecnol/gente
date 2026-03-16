<?php

namespace App\Rules\Escala;

use Illuminate\Contracts\Validation\Rule;

class ChecarPeriodoPosterior implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $value = explode('/', $value);
        $valor = date('Y-m-1', strtotime("$value[1]-$value[0]"));
        $atual = date('Y-m-1', strtotime(date('Y-m')));

        return ($valor > $atual) ? true : false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Só é possível cadastrar a escala para competências posteriores a atual.';
    }
}
