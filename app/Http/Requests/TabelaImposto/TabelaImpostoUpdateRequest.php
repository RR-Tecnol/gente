<?php

namespace App\Http\Requests\TabelaImposto;

use Illuminate\Foundation\Http\FormRequest;

class TabelaImpostoUpdateRequest extends TabelaImpostoCreateRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras['TABELA_IMPOSTO_ID'] = ['required'];
        return $regras;
    }
}
