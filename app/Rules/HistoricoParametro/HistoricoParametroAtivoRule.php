<?php

namespace App\Rules\HistoricoParametro;

use App\Models\ParametroFinanceiro;
use App\MyLibs\Utilitarios;
use Illuminate\Contracts\Validation\Rule;

class HistoricoParametroAtivoRule implements Rule
{
    private $parametro;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($parametro)
    {
        $this->parametro = ParametroFinanceiro::find($parametro);
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
        if ($value == null)
            return true;

        $periodo = Utilitarios::conveterPeriodo($value);
        $historicoUltimo = $this->parametro->historicoUltimo;

        if ($historicoUltimo) {
            $inicio = Utilitarios::conveterPeriodo($historicoUltimo->HISTORICO_PARAMETRO_INICIO);
            $fim = Utilitarios::conveterPeriodo($historicoUltimo->HISTORICO_PARAMETRO_FIM);
            if ($periodo >= $inicio && $periodo <= $fim)
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
        return 'Não é possível cadastrar vigência conflitante para um mesmo parâmetro financeiro.';
    }
}
