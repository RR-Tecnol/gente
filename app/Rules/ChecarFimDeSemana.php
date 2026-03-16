<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ChecarFimDeSemana implements Rule
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
        $semana = date('N', strtotime($value));
        return ($semana == "7" || $semana == "6") ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return ':attribute deve ser dia útil.';
    }
}
