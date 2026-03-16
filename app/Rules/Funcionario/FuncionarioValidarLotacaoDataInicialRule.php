<?php

namespace App\Rules\Funcionario;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;

class FuncionarioValidarLotacaoDataInicialRule implements Rule
{
    private $funcionarioDataInicio;

    public function __construct($funcionarioDataInicio)
    {
        $this->funcionarioDataInicio = $funcionarioDataInicio;
    }

    public function passes($attribute, $value)
    {
        $lotacoes = $value;
        foreach ($lotacoes as $lotacao) {
            $funcionarioDataInicio = $this->funcionarioDataInicio == null ? null : Carbon::parse($this->funcionarioDataInicio)->format("Y-m-d");
            $lotacaoDataInicio = $lotacao['LOTACAO_DATA_INICIO'] == null ? null : Carbon::parse($lotacao['LOTACAO_DATA_INICIO'])->format("Y-m-d");
            if ($funcionarioDataInicio > $lotacaoDataInicio) {
                return false;
            }
        }
        return true;
    }

    public function message()
    {
        return 'Existem <b>LOTAÇÕES</b> com <b>DATA INICIAL</b> anterior à data inicial do funcionário';
    }
}
