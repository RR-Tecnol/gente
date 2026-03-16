<?php

namespace App\Http\Requests\Profissao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ProfissaoCreateRequest extends FormRequest
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
            "PROFISSAO_DESCRICAO" => ["required","unique:PROFISSAO","min:3"],
            "PROFISSAO_ESCOLARIDADE" => ["required"],
        ];
    }

    public function attributes()
    {
        return[
            "PROFISSAO_ID" => "<b>PROFISSAO ID</b>",
            "PROFISSAO_DESCRICAO" => "<b>DESCRIÇÃO</b>",
            "PROFISSAO_ESCOLARIDADE" => "<b>ESCOLARIDADE</b>",
        ];
    }
}
