<?php

namespace App\Rules\Escala;

use App\Models\Escala;
use Illuminate\Contracts\Validation\Rule;

class ChecarEscalaDeferida implements Rule
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
        $escala = Escala::find($value);

        return ($escala->historicoUltimo->statusEscala->COLUNA_ID === 4) ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Não é possível editar uma escala deferida.';
    }
}
