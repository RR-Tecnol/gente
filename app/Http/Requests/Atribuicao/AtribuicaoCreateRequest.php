<?php

namespace App\Http\Requests\Atribuicao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AtribuicaoCreateRequest extends FormRequest
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
            "ATRIBUICAO_TIPO" => ['required'],
            "ATRIBUICAO_NOME" => ['required'],
            "ATRIBUICAO_SIGLA" => ['required'],
            "ATRIBUICAO_ESCOLARIDADE" => ['required'],
            "ATRIBUICAO_GESTAO" => ['required'],
            "ATRIBUICAO_ATIVA" => ['required'],
        ];
    }

    public function attributes()
    {
        return [
            "ATRIBUICAO_ID" => '<b>ATRIBUICAO ID</b>',
            "ATRIBUICAO_TIPO" => '<b>TIPO DE ATRIBUIÇÃO</b>',
            "ATRIBUICAO_NOME" => '<b>DESCRIÇÃO</b>',
            "ATRIBUICAO_SIGLA" => '<b>SIGLA</b>',
            "ATRIBUICAO_GESTAO" => '<b>GESTÃO</b>',
            "ATRIBUICAO_ESCOLARIDADE" => '<b>ESCOLARIDADE</b>',
            "ATRIBUICAO_ATIVA" => '<b>ATIVA</b>',
        ];
    }
}
