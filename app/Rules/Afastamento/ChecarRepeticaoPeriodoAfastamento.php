<?php

namespace App\Rules\Afastamento;

use App\Models\Afastamento;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;

class ChecarRepeticaoPeriodoAfastamento implements Rule
{
    private $funcionarioId;
    private $afastamentoId;
    private $message;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($funcionarioId, $afastamentoId = null, $message = null)
    {
        $this->funcionarioId = $funcionarioId;
        $this->afastamentoId = $afastamentoId;
        $this->message = $message;
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
        $periodo = Afastamento::where('AFASTAMENTO_DATA_INICIO', '<=', $value)
            ->where('AFASTAMENTO_DATA_FIM', '>=', $value)
            ->where('FUNCIONARIO_ID', $this->funcionarioId)
            ->when($this->afastamentoId, function (Builder $query) {
                $query->whereKeyNot($this->afastamentoId);
            })
            ->first();
        return $periodo ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message ?: 'Não pode haver mais de um afastamento ativo para uma mesma lotação.';
    }
}
