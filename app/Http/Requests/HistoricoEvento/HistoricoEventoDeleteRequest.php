<?php

namespace App\Http\Requests\HistoricoEvento;

use Illuminate\Foundation\Http\FormRequest;

class HistoricoEventoDeleteRequest extends HistoricoEventoCreateRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'HISTORICO_EVENTO_ID' => ['required']
        ];
    }
}
