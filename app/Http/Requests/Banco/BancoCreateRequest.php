<?php

namespace App\Http\Requests\Banco;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class BancoCreateRequest extends FormRequest
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
            'BANCO_CODIGO' => ['required','unique:BANCO'],
            'BANCO_NOME' => ['required','unique:BANCO'],
            'BANCO_OFICIAL' => ['required','integer'],
            'BANCO_ATIVO' => ['required','integer'],
        ];
    }

    public function attributes() {
        return [
            "BANCO_ID" => "<strong>BANCO ID</strong>",
            "BANCO_CODIGO" => "<strong>CODIGO</strong>",
            "BANCO_NOME" => "<strong>NOME</strong>",
            "BANCO_OFICIAL" => "<strong>OFICIAL</strong>",
            "BANCO_ATIVO" => "<strong>ATIVO</strong>"
        ];
    }
}
