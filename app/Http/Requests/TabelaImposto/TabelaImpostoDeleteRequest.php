<?php

namespace App\Http\Requests\TabelaImposto;

use Illuminate\Foundation\Http\FormRequest;

class TabelaImpostoDeleteRequest extends TabelaImpostoCreateRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'TABELA_IMPOSTO_ID' => ['required']
        ];
    }
}
