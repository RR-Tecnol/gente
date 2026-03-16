<?php

namespace App\Http\Requests\Pessoa;

use Illuminate\Validation\Rule;

class PessoaUpdateRequest extends PessoaCreateRequest
{
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('PESSOA')->ignore($this->request->all()["PESSOA_ID"], "PESSOA_ID");

        $regras = parent::rules();
        $regras["PESSOA_ID"] = ["required", "integer"];
        $regras["PESSOA_CPF_NUMERO"] = ["required", "cpf", $uniqueIgnoreId];
        return $regras;
    }
}
