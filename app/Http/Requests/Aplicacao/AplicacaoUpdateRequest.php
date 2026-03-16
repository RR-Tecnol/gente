<?php

namespace App\Http\Requests\Aplicacao;

use Illuminate\Foundation\Http\FormRequest;

class AplicacaoUpdateRequest extends AplicacaoCreateRequest
{
    

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras['APLICACAO_ID'] = ['required'];
        return $regras;
    }
}
