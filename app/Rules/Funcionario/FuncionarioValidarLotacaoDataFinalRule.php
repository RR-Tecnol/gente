<?php

namespace App\Rules\Funcionario;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;

class FuncionarioValidarLotacaoDataFinalRule implements Rule
{
    private $funcionarioDataFim;

    public function __construct($funcionarioDataFim)
    {
        $this->funcionarioDataFim = $funcionarioDataFim;
    }

    public function passes($attribute, $value)
    {
        $lotacoes = $value;
        foreach ($lotacoes as $lotacao) {
            if ($this->funcionarioDataFim != null) {
                $funcionarioDataFim = $this->funcionarioDataFim == null ? null : Carbon::parse($this->funcionarioDataFim)->format("Y-m-d");
                $lotacaoDataFim = $lotacao['LOTACAO_DATA_FIM'] == null ? null : Carbon::parse($lotacao['LOTACAO_DATA_FIM'])->format("Y-m-d");
                if ($funcionarioDataFim > $lotacaoDataFim) {
                    return false;
                }
            }
        }
        return true;
    }

    public function message()
    {
        return 'Existem <b>LOTAÇÕES</b> não finalizadas ou com <b>DATA FINAL</b> anterior à data final do funcionário';
    }
}
