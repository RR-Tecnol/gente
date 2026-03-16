<?php

namespace App\Http\Requests\PessoaProfissao;

class PessoaProfissaoUpdateRequest extends PessoaProfissaoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["PESSOA_PROFISSAO_ID"] = ["required","integer"];
        return $regras;
    }
}
