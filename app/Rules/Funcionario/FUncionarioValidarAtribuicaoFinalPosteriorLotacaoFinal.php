<?php

namespace App\Rules\Funcionario;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;

class FUncionarioValidarAtribuicaoFinalPosteriorLotacaoFinal implements Rule
{
    public function __construct() {}

    public function passes($attribute, $value)
    {
        $lotacoes = $value;
        foreach ($lotacoes as $lotacao) {
            $lotacaoDataFim = $lotacao['LOTACAO_DATA_FIM'] == null ? null : Carbon::parse($lotacao['LOTACAO_DATA_FIM'])->format("Y-m-d");
            foreach ($lotacao['atribuicaoLotacoes'] as $atribuicaoLotacao) {
                $atribuicaoLotacaoFim = $atribuicaoLotacao['ATRIBUICAO_LOTACAO_FIM'] == null ? null : Carbon::parse($atribuicaoLotacao['ATRIBUICAO_LOTACAO_FIM'])->format("Y-m-d");
                if (($atribuicaoLotacaoFim > $lotacaoDataFim) && $lotacaoDataFim != null) {
                    return false;
                }
            }
        }
        return true;
    }

    public function message()
    {
        return 'Existem <b>ATRIBUIÇÕES</b> com <b>DATA FINAL</b> maior que <b>DATA FINAL</b> da <b>LOTAÇÃO</b>';
    }
}
