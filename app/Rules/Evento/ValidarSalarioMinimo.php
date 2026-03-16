<?php

namespace App\Rules\Evento;

use App\Models\HistoricoParametro;
use Illuminate\Contracts\Validation\Rule;

class ValidarSalarioMinimo implements Rule
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
        $salarioMinimo = HistoricoParametro::salarioMinimo();
        if ($value == 3) {
            return $salarioMinimo ? true : false;
        } else {
            return true;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Não possui salario minimo disponivel.';
    }
}
