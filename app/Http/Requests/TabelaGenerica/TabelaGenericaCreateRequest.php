<?php

namespace App\Http\Requests\TabelaGenerica;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class TabelaGenericaCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check();
    }

    public function rules()
    {
        return [
            "TABELA_ID" => ["required","integer"],
            "DESCRICAO" => ["required","unique:TABELA_GENERICA","min:3"],
        ];
    }

    public function attributes()
    {
        return[
            "TABELA_GENERICA_ID" => "<b>TABELA GENERICA ID</b>",
            "TABELA_ID" => "<b>TABELA</b>",
            "DESCRICAO" => "<b>DESCRIÇÃO</b>",
        ];
    }
}
