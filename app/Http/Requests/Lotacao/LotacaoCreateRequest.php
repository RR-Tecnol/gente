<?php

namespace App\Http\Requests\Lotacao;

use App\Rules\ChecarAcessoUsuarioSetor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class LotacaoCreateRequest extends FormRequest {
        public function authorize() {
        return Auth::check();
    }

    public function rules() {
        return [
            "FUNCIONARIO_ID"        => ["required", "integer"],
            "VINCULO_ID"            => ["required", "integer"],
            "SETOR_ID"              => ["required", "integer", new ChecarAcessoUsuarioSetor],
            "LOTACAO_DATA_INICIO"   => ["required", "date"],
            "LOTACAO_DATA_FIM"      => ["nullable", "date", "after:LOTACAO_DATA_INICIO"],
            "LOTACAO_TIPO_FIM"      => ["required_with:LOTACAO_DATA_FIM"],
//            "atribuicaoLotacoes"    => ["required"]
        ];
    }

    public function attributes() {
        return [
            "FUNCIONARIO_ID"        => "<b>FUNCIONARIO</b>",
            "SETOR_ID"              => "<b>SETOR</b>",
            "VINCULO_ID"            => "<b>VÍNCULO</b>",
            "LOTACAO_DATA_INICIO"   => "<b>DATA INICIAL</b>",
            "LOTACAO_DATA_FIM"      => "<b>DATA FINAL</b>",
            "LOTACAO_TIPO_FIM"      => "<b>MOTIVO</b>",
        ];
    }

    public function messages() {
        return [
            "LOTACAO_DATA_FIM.after" => "O campo :attribute deve conter uma data posterior a <b>DATA INÍCIO</b>.",
//            "atribuicaoLotacoes.required" => "Nenhuma <b>ATRIBUIÇÃO</b> informada para a lotação"
        ];
    }


}
