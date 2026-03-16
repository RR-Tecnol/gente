<?php

namespace App\Rules\Evento;

use Illuminate\Contracts\Validation\Rule;

class EventoTipoSalario implements Rule
{
    private $evento;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($evento)
    {
        $this->evento = $evento;
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
        $salario = $this->evento->EVENTO_SALARIO;
        if ($salario) {
            if ($value == 1 || ($value == 2))
                return false;
        }
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Eventos do tipo SALARIO não podem ter a forma de cálculo do tipo PERCENTUAL SOBRE SALÁRIO e PERCENTUAL SOBRE PROVENTOS';
    }
}
