<?php

namespace App\Http\Requests\HistoricoParametro;


class HistoricoParametroUpdateRequest extends HistoricoParametroCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["HISTORICO_PARAMETRO_ID"] = ["required"];
        $regras["HISTORICO_PARAMETRO_INICIO"] = ["required"];
        $regras["HISTORICO_PARAMETRO_FIM"] = ['nullable','after_or_equal:HISTORICO_PARAMETRO_INICIO'];
        return $regras;
    }
}
