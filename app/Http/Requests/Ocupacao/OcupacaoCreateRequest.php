<?php

namespace App\Http\Requests\Ocupacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class OcupacaoCreateRequest extends FormRequest
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
            "OCUPACAO_NOME" => ["required","unique:OCUPACAO"],
            "OCUPACAO_CBO" => ["required","integer"],
            "OCUPACAO_ATIVA" => ["required","integer"]
        ];
    }

    public function attributes()
    {
        return[
            "OCUPACAO_ID" => "<b>OCUPAÇÃO ID</b>",
            "OCUPACAO_NOME" => "<b>NOME</b>",
            "OCUPACAO_CBO" => "<b>CBO</b>",
            "OCUPACAO_ATIVA" => "<b>ATIVO</b>",
        ];
    }
}
