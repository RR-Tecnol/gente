<?php

namespace App\Http\Requests\AnexoAfastamento;

class AnexoAfastamentoUpdateRequest extends AnexoAfastamentoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["ANEXO_AFASTAMENTO_ID"] = ["required","integer"];
        return $regras;
    }
}
