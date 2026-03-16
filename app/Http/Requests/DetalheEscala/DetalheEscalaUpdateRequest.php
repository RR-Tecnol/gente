<?php

namespace App\Http\Requests\DetalheEscala;

use App\Rules\Escala\ChecarEscalaDeferida;

class DetalheEscalaUpdateRequest extends DetalheEscalaCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["ESCALA_ID"] = [new ChecarEscalaDeferida];
        unset($regras["FUNCIONARIO_ID"]);
        unset($regras["DETALHE_ESCALA_DATA"]);
        return $regras;
    }
}
