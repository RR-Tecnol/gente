<?php

namespace App\Http\Requests\Vinculo;
use Illuminate\Validation\Rule;

class VinculoUpdateRequest extends VinculoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('VINCULO')->ignore($this->request->all()["VINCULO_ID"],"VINCULO_ID");
        $regras = parent::rules();
        $regras["VINCULO_ID"] = ["required","integer"];
        $regras["VINCULO_DESCRICAO"] = ["required",$uniqueIgnoreId];
        $regras["VINCULO_SIGLA"] = ["required",$uniqueIgnoreId];
        return $regras;
    }
}
