<?php

namespace App\Http\Requests\DetalheEscalaAutoriza;

use App\Rules\ChecarAcessoUsuarioSetor;
use Illuminate\Foundation\Http\FormRequest;

class DetalheEscalaAutorizaCreateRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "DETALHE_ESCALA_AUTORIZA_JUSTIFICATIVA" => ["required"],
            "SETOR_ID" => [new ChecarAcessoUsuarioSetor],
        ];
    }

    public function attributes() {
        return [
            "DETALHE_ESCALA_AUTORIZA_JUSTIFICATIVA" => "<b>JUSTIFICATIVA</b>"
        ];
    }
}
