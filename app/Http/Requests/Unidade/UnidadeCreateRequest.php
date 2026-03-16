<?php

namespace App\Http\Requests\Unidade;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UnidadeCreateRequest extends FormRequest
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
            "UNIDADE_NOME" => ["required",'unique:UNIDADE',"min:3"],
            "UNIDADE_CNES" => ["required", 'unique:UNIDADE'],
            // "UNIDADE_TIPO" => ["required"],
            // 'UNIDADE_PORTE' => ['required'],
            "BAIRRO_ID" => ["required"],
//            "UNIDADE_ENDERECO" => ["required","min:3"],
            'UNIDADE_ATIVA' => ['required']
        ];
    }

    public function attributes()
    {
        return[
            "UNIDADE_ID" => "<b>ID DA UNIDADE</b>",
            "UNIDADE_NOME" => "<b>NOME DA UNIDADE</b>",
            "UNIDADE_CNES" => "<b>CNES</b>",
            "UNIDADE_TIPO" => "<b>TIPO DE UNIDADE</b>",
            "UNIDADE_PORTE" => "<b>PORTE DA UNIDADE</b>",
            "BAIRRO_ID" => "<b>BAIRRO</b>",
//            "UNIDADE_ENDERECO" => "<b>ENDEREÇO</b>",
            'UNIDADE_ATIVA' => "<b>ATIVO</b>"
        ];
    }
}
