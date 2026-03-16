<?php

namespace App\Rules\Funcionario;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;

class FuncionarioValidarFuncionarioDataFimLotacaoEncerrada implements Rule
{
    private $funcionarioDataFim;

    public function __construct($funcionarioDataFim)
    {
        $this->funcionarioDataFim = $funcionarioDataFim;
    }

    public function passes($attribute, $value)
    {
        $lotacoes = $value;
        $filtro = array_filter($lotacoes, function ($r) {
            return $r['LOTACAO_DATA_FIM'] == null;
        });
        return !(count($filtro) > 0 && $this->funcionarioDataFim != null);
    }

    public function message()
    {
        return 'Existem <b>LOTAÇÕES</b> não encerradas';
    }
}
