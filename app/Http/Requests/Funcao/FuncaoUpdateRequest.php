<?php

namespace App\Http\Requests\Funcao;

use Illuminate\Validation\Rule;

class FuncaoUpdateRequest extends FuncaoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('FUNCAO')->ignore($this->request->all()["FUNCAO_ID"],"FUNCAO_ID");
        return [
            "FUNCAO_ID" => ["required","integer"],
            "FUNCAO_NOME" => ["required",$uniqueIgnoreId,"min:3"],
            "FUNCAO_SIGLA" => ["required",$uniqueIgnoreId,"min:3","max:20"],
        ];
    }

}
