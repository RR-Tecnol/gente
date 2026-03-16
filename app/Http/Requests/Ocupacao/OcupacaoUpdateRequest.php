<?php

namespace App\Http\Requests\Ocupacao;

use Illuminate\Validation\Rule;

class OcupacaoUpdateRequest extends OcupacaoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('OCUPACAO')->ignore($this->request->all()["OCUPACAO_ID"],"OCUPACAO_ID");
        return [
            "OCUPACAO_ID" => ["required","integer"],
            "OCUPACAO_NOME" => ["required",$uniqueIgnoreId],
            "OCUPACAO_CBO" => ["required","integer"],
        ];
    }
}
