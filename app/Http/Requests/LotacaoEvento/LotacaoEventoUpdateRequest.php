<?php

namespace App\Http\Requests\LotacaoEvento;

class LotacaoEventoUpdateRequest extends LotacaoEventoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "LOTACAO_EVENTO_ID" => ["required", "integer"]
        ];
    }
}
