<?php

namespace App\Http\Requests\Atribuicao;

use Illuminate\Foundation\Http\FormRequest;

class AtribuicaoUpdateRequest extends AtribuicaoCreateRequest
{
    

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras['ATRIBUICAO_ID'] = ['required'];
        return $regras;
    }
}
