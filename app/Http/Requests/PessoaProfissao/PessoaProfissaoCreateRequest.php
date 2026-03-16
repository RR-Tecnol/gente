<?php

namespace App\Http\Requests\PessoaProfissao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PessoaProfissaoCreateRequest extends FormRequest
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
            "PESSOA_ID" => ["required","integer"],
            "PROFISSAO_ID" => ["required","integer"],
        ];
    }

    public function attributes()
    {
        return[
            "PESSOA_PROFISSAO_ID" => "<b>PESSOA PROFISSAO ID</b>",
            "PESSOA_ID" => "<b>PESSOA</b>",
            "PROFISSAO_ID" => "<b>PROFISSÃO</b>",
        ];
    }

    public function messages()
    {
        return [
            "PROFISSAO_ID.required" => 'Nenhuma :attribute foi selecionada no campo <b>DESCRIÇÃO</b>.'
        ];
    }
}
