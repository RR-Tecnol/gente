<?php

namespace App\Rules\Escala;

use Illuminate\Contracts\Validation\Rule;

class ChecarFaltaAtrasoPeriodoCompAtual implements Rule
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

        if ($value <= date('Y-m-d') && $value >= date('Y-m-d', strtotime('first day of last month')))
            return true;
        else return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Só será possível lançar faltas e atrasos para escalas da competência atual ou anterior.';
    }
}
