<?php

namespace App\Http\Requests\Lotacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LotacaoUpdateRequest extends LotacaoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["LOTACAO_ID"] = ['required',"integer"];
        return $regras;
    }

}
