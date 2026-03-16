<?php

namespace App\Http\Requests\SubstituicaoEscala;

class SubstituicaoEscalaUpdateRequest extends SubstituicaoEscalaCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["SUBSTITUICAO_ESCALA_ID"] = ["required","integer"];
        return $regras;
    }
}
