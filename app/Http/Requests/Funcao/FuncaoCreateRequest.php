<?php

namespace App\Http\Requests\Funcao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FuncaoCreateRequest extends FormRequest
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
            "FUNCAO_NOME" => ["required","unique:FUNCAO","min:3"],
            "FUNCAO_SIGLA" => ["required","unique:FUNCAO","min:3","max:20"],
        ];
    }

    public function attributes()
    {
        return[
            "FUNCAO_ID" => "<b>FUNÇÃO ID</b>",
            "FUNCAO_NOME" => "<b>DESCRIÇÃO</b>",
            "FUNCAO_SIGLA" => "<b>SIGLA</b>",
            "FUNCAO_ATIVO" => "<b>ATIVO</b>",
        ];
    }
}
