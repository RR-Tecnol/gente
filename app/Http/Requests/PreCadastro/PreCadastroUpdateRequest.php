<?php

namespace App\Http\Requests\PreCadastro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PreCadastroUpdateRequest extends FormRequest
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
        $uniqueIgnoreId = Rule::unique('PESSOA')->ignore($this->request->all()["PESSOA_ID"], "PESSOA_ID");

        return [
            "PESSOA_CPF_NUMERO" => ["required", "cpf", $uniqueIgnoreId],
            "PESSOA_NOME" => ["required", "string"],
            "PESSOA_PRE_CADASTRO" => ["required", "boolean"],
            "lotacoes" => ["required", "array"],
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            "PESSOA_CPF_NUMERO" => "<b>CPF</b>",
            "PESSOA_NOME" => "<b>Nome</b>",
            "PESSOA_PRE_CADASTRO" => "<b>Pré-cadastro</b>",
        ];
    }
}
