<?php

namespace App\Http\Requests\SetorAtribuicao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SetorAtribuicaoCreateRequest extends FormRequest
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
            "SETOR_ID" => ['required','integer'],
            "ATRIBUICAO_ID" => ['required','integer'],
            "SETOR_ATRIBUICAO_QTD" => ['required','integer','min:1'],
        ];

    }

    public function attributes()
    {
        return[
            "SETOR_ATRIBUICAO_ID" => "<b>SETOR ATRIBUIÇÃO</b>",
            "SETOR_ID" => "<b>SETOR</b>",
            "ATRIBUICAO_ID" => "<b>ATRIBUIÇÃO</b>",
            "SETOR_ATRIBUICAO_QTD" => "<b>QUANTIDADE</b>",
        ];
    }

}
