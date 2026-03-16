<?php

namespace App\Http\Requests\PreCadastro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PreCadastroCreateRequest extends FormRequest
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
            "PESSOA_CPF_NUMERO" => ["required", "cpf", "unique:PESSOA,PESSOA_CPF_NUMERO"],
            "PESSOA_NOME" => ["required", "string"],
            "VINCULO_ID" => ["required", "integer"],
            "SETOR_ID" => ["required", "integer"],
            "ATRIBUICAO_ID" => ["required", "integer"],
            "ATRIBUICAO_LOTACAO_CARGA_HORARIA" => ["required", "string"],
        ];
    }

    public function attributes()
    {
        return [
            "PESSOA_CPF_NUMERO"     => "<b>CPF</b>",
            "PESSOA_NOME"   => "<b>NOME</b>",
            "VINCULO_ID"         => "<b>VÍNCULO</b>",
            "UNIDADE_ID"         => "<b>UNIDADE</b>",
            "SETOR_ID"         => "<b>SETOR</b>",
            "ATRIBUICAO_ID"         => "<b>CARGO</b>",
            "ATRIBUICAO_LOTACAO_CARGA_HORARIA"         => "<b>CARGA HORÁRIA</b>",
        ];
    }
}
