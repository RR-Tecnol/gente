<?php

namespace App\Http\Requests\Unidade;

use Illuminate\Validation\Rule;

class UnidadeUpdateRequest extends UnidadeCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $uniqueIgnoreId = Rule::unique('UNIDADE')->ignore($this->request->all()["UNIDADE_ID"],"UNIDADE_ID");
        $regras["UNIDADE_NOME"] = ["required",$uniqueIgnoreId];
        $regras["UNIDADE_CNES"] = ["required",$uniqueIgnoreId];
        $regras["UNIDADE_ID"] = ["required","integer"];
        return $regras;
    }

}
