<?php

namespace App\Http\Requests\Cidade;

use Illuminate\Foundation\Http\FormRequest;

class CidadeUpdateRequest extends CidadeCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras['CIDADE_ID'] = ['required'];
        return $regras;
    }
}
