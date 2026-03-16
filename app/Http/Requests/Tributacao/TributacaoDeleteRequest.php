<?php

namespace App\Http\Requests\Tributacao;

class TributacaoDeleteRequest extends TributacaoCreateRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'TRIBUTACAO_ID'=>['required']
        ];
    }
}
