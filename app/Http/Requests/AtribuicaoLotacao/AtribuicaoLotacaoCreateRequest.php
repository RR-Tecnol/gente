<?php

namespace App\Http\Requests\AtribuicaoLotacao;

use App\Rules\CargaHorariaAtribuicaoPorteUnidadeRule;
use App\Rules\FuncionarioEscolariadeAtribuicaoRule;
use Illuminate\Foundation\Http\FormRequest;

class AtribuicaoLotacaoCreateRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "FUNCIONARIO_ID"                    => ["required"],
            "LOTACAO_DATA_INICIO"               => ["required"],
//            "LOTACAO_DATA_FIM"                  => ["required"],
            "ATRIBUICAO_ID"                     => [
                "bail",
                "required",
                new FuncionarioEscolariadeAtribuicaoRule($this->input()),
                new CargaHorariaAtribuicaoPorteUnidadeRule($this->input()),
            ],
            "LOTACAO_ID"                        => ["required"],
            "ATRIBUICAO_LOTACAO_CARGA_HORARIA"  => ["required"],

            "ATRIBUICAO_LOTACAO_INICIO"         => ["required", "date", "after_or_equal:LOTACAO_DATA_INICIO"],
            "ATRIBUICAO_LOTACAO_FIM"            => ["nullable", "date", "before_or_equal:LOTACAO_DATA_FIM", "after:ATRIBUICAO_LOTACAO_INICIO"],
            "TIPO_CALCULO_ID"  => ["required"],
            "ATRIBUICAO_LOTACAO_VALOR"  => ["required"],
            "PROGRAMA_ID"  => ["required"],
        ];
    }

    public function attributes() {
        return [
            "ATRIBUICAO_ID"                     => "<b>ATRIBUIÇÃO</b>",
            "LOTACAO_ID"                        => "<b>LOTAÇÃO</b>",
            "ATRIBUICAO_LOTACAO_CARGA_HORARIA"  => "<b>CARGA HORÁRIA</b>",
            "ATRIBUICAO_LOTACAO_INICIO"         => "<b>DATA INICIAL</b>",
            "ATRIBUICAO_LOTACAO_FIM"            => "<b>DATA FINAL</b>",
            "FUNCIONARIO_ID"                    => "<b>FUNCIONÁRIO ID</b>",
            "LOTACAO_DATA_INICIO"               => "<b>DATA INICIAL DA LOTAÇÃO</b>",
            "LOTACAO_DATA_FIM"                  => "<b>DATA FINAL DA LOTAÇÃO</b>",
            // "TIPO_CALCULO_ID"                   => "<b>TIPO DE CÁLCULO</b>",
            // "ATRIBUICAO_LOTACAO_VALOR"          => "<b>VALOR</b>",
            // "PROGRAMA_ID"          => "<b>PROGRAMA</b>",
        ];
    }
}
