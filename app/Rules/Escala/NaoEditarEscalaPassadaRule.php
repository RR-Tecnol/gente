<?php

namespace App\Rules\Escala;

use App\Models\Escala;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;

class NaoEditarEscalaPassadaRule implements Rule
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
        $copetencia = explode('/', $escala->ESCALA_COMPETENCIA);
        $copetencia = "$copetencia[1]$copetencia[0]";
        return ($copetencia < date('Ym')) ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Não é permitido alterar escalas de competências passadas.';
    }
}
