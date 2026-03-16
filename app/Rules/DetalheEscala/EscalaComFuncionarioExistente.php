<?php

namespace App\Rules\DetalheEscala;

use App\Models\Escala;
use Illuminate\Contracts\Validation\Rule;

class EscalaComFuncionarioExistente implements Rule
{
    private $idEscala;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($idEscala)
    {
        $this->idEscala = $idEscala;
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
        $escala = Escala::where('ESCALA_ID', $this->idEscala)
            ->whereHas('detalheEscalas', function ($query) use ($value) {
                $query->where('FUNCIONARIO_ID', $value);
            })
            ->first();
        return ($escala) ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Funcionario selecionado já existe na escala.';
    }
}
