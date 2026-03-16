<?php

namespace App\Http\Requests\HistoricoEvento;

use Illuminate\Foundation\Http\FormRequest;

class HistoricoEventoUpdateRequest extends HistoricoEventoCreateRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["HISTORICO_EVENTO_ID"] = ["required"];

        $regras["HISTORICO_EVENTO_INICIO"] = ['required'];
        $regras["HISTORICO_EVENTO_FIM"] = ['nullable','after_or_equal:HISTORICO_EVENTO_INICIO'];
        return $regras;
    }
}
