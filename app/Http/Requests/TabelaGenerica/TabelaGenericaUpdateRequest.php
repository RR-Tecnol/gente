<?php

namespace App\Http\Requests\TabelaGenerica;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TabelaGenericaUpdateRequest extends TabelaGenericaCreateRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('TABELA_GENERICA')->ignore($this->request->all()["TABELA_GENERICA_ID"],"TABELA_GENERICA_ID");
        return [
            "TABELA_GENERICA_ID" => ["required","integer"],
            "DESCRICAO" => ["required",$uniqueIgnoreId,"min:3"],
        ];
    }
}
