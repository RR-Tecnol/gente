<?php

namespace App\Rules\Evento;

use App\Models\Evento;
use Illuminate\Contracts\Validation\Rule;

class HistoricoEventoAtivoRule implements Rule
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
        return $this->evento->historicoEvento ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Só poderá haver um histórico de evento ativo.';
    }
}
