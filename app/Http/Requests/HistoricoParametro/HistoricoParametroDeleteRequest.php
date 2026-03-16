<?php

namespace App\Http\Requests\HistoricoParametro;

class HistoricoParametroDeleteRequest extends HistoricoParametroCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'HISTORICO_PARAMETRO_ID' => ['required']
        ];
    }
}
