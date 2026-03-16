<?php

namespace App\Rules\Escala;

use App\Models\Escala;
use Illuminate\Contracts\Validation\Rule;

class ChecarRepeticaoSetorCompetencia implements Rule
{

    private $setor;
    private $tipoEscala;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($setor, $tipoEscala)
    {
        $this->setor = $setor;
        $this->tipoEscala = $tipoEscala;
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
        $periodo = explode('/', $value);

        $escalaExistente = Escala::where('SETOR_ID', '=', $this->setor)
            ->where('TIPO_ESCALA_ID', '=', $this->tipoEscala)
            ->where($attribute, '=', "$periodo[1]$periodo[0]")
            ->first();

        return !$escalaExistente;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Já existe uma escala cadastrada com o mesmo setor, tipo de escala e competência.';
    }
}
