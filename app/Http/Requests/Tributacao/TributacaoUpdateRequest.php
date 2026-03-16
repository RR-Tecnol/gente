<?php

namespace App\Http\Requests\Tributacao;

use Illuminate\Foundation\Http\FormRequest;

class TributacaoUpdateRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras['TRIBUTACAO_ID'] = ['required'];
        return $regras;
    }
}
