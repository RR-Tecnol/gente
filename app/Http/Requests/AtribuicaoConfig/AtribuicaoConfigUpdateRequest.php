<?php

namespace App\Http\Requests\AtribuicaoConfig;

use Illuminate\Foundation\Http\FormRequest;

class AtribuicaoConfigUpdateRequest extends AtribuicaoConfigCreateRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'ATRIBUICAO_CONFIG_ID'=> ['required'],
            'HIST_ATRIBUICAO_CONFIG_VALOR'=> ['required'],
        ];
    }
}
