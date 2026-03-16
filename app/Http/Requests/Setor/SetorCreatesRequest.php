<?php

namespace App\Http\Requests\Setor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SetorCreatesRequest extends FormRequest
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "UNIDADE_ID" => ["required"],
            "setores" => ["required"],
        ];
    }

    public function attributes()
    {
        return[
            "SETOR_ID" => "<b>SETOR ID</b>",
            "setores" => "<b>SETORES</b>",
            "SETOR_NOME" => "<b>NOME</b>",
            "SETOR_SIGLA" => "<b>SIGLA</b>",
            "SETOR_ATIVO" => "<b>ATIVO</b>",
            "UNIDADE_ID" => '<b>UNIDADE</b>'

        ];
    }
}
