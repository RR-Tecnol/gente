<?php

namespace App\Http\Requests\Profissao;

use Illuminate\Validation\Rule;

class ProfissaoUpdateRequest extends ProfissaoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('PROFISSAO')->ignore($this->request->all()["PROFISSAO_ID"],"PROFISSAO_ID");
        return [
            "PROFISSAO_ID" => ["required","integer"],
            "PROFISSAO_DESCRICAO" => ["required",$uniqueIgnoreId,"min:3"],
        ];
    }

}
