<?php

namespace App\Http\Requests\HistoricoEscala;

use App\Rules\ChecarAcessoUsuarioSetor;
use App\Rules\HistoricoEscala\EscalaPossuiAlertaRule;
use Illuminate\Foundation\Http\FormRequest;

class HistoricoEscalaCreateRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            '*' => 'bail',
            "escala.ESCALA_ID"                          => [
                "bail",
                "required",
                new EscalaPossuiAlertaRule($this->input("escala"))
            ],
            "historicoEscala.HISTORICO_ESCALA_STATUS"   => ["required"],
            "escala.SETOR_ID" => [new ChecarAcessoUsuarioSetor]
        ];
    }

    public function attributes() {
        return [
            "escala.ESCALA_ID"                          => "<b>ESCALA</b>",
            "historicoEscala.HISTORICO_ESCALA_STATUS"   => "<b>STATUS DA ESCALA</b>"
        ];
    }

    public function messages() {
        return [
            "historicoEscala.HISTORICO_ESCALA_STATUS.required" => "Você não informou se a escala está <b>DEFERIDA</b> ou <b>INDEFERIDA</b>"
        ];
    }
}
