<?php

namespace App\Http\Requests\FimLotacao;

class FimLotacaoUpdateRequest extends FimLotacaoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["FIM_LOTACAO_ID"] = ["required","integer"];
        return $regras;
    }
}
