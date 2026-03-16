<?php

namespace App\Rules\Ferias;

use App\Models\Ferias;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;

class ChecarRepeticaoPeriodoAquisitivoFerias implements Rule
{
    private $funcionarioId;
    private $feriasId;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($funcionarioId, $feriasId = null)
    {
        $this->funcionarioId = $funcionarioId;
        $this->feriasId = $feriasId;
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
        $pAquisitivo = Ferias::where($attribute, $value)
            ->where('FUNCIONARIO_ID', $this->funcionarioId)
            ->when($this->feriasId, function (Builder $query) {
                $query->whereKeyNot($this->feriasId);
            })
            ->first();
        return $pAquisitivo ? false : true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Não pode haver duplicidade de períodos aquisitivos de férias.';
    }
}
