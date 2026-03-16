<?php

namespace App\Rules\Funcionario;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;

class FuncionarioValidarAtribuicaoLotacaoFimRule implements Rule
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
            $funcionarioDataFim = $this->funcionarioDataFim == null ? null : Carbon::parse($this->funcionarioDataFim)->format("Y-m-d");
            $lotacaoDataFim = $lotacao['LOTACAO_DATA_FIM'] == null ? null : Carbon::parse($lotacao['LOTACAO_DATA_FIM'])->format("Y-m-d");
            foreach ($lotacao['atribuicaoLotacoes'] as $atribuicaoLotacao) {
                $atribuicaoLotacaoFim = $atribuicaoLotacao['ATRIBUICAO_LOTACAO_FIM'] == null ? null : Carbon::parse($atribuicaoLotacao['ATRIBUICAO_LOTACAO_FIM'])->format("Y-m-d");
                if ($atribuicaoLotacaoFim < $lotacaoDataFim || $atribuicaoLotacaoFim < $funcionarioDataFim) {
                    return false;
                }
            }
        }
        return true;
    }

    public function message()
    {
        return 'Existem <b>ATRIBUIÇÕES</b> não finalizadas ou com <b>DATA FINAL</b> anterior à data final do funcionário ou da lotação';
    }
}
