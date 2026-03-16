<?php

namespace App\Rules\Ferias;

use App\Models\Ferias;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ChecarRepeticaoPeriodoFerias implements Rule
{
    private $funcionarioId;
    private $feriasId;
    private $message;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($funcionarioId, $feriasId = null, $message = null)
    {
        $this->funcionarioId = $funcionarioId;
        $this->feriasId = $feriasId;
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
        $periodo = Ferias::where('FERIAS_DATA_INICIO', '<=', $value)
            ->where('FERIAS_DATA_FIM', '>=', $value)
            ->where('FUNCIONARIO_ID', $this->funcionarioId)
            ->when($this->feriasId, function (Builder $query) {
                $query->whereKeyNot($this->feriasId);
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
        return $this->message ?: 'Não pode haver conflito de datas para o registro de férias de uma mesma lotação.';
    }
}
