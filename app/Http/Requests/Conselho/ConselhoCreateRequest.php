<?php

namespace App\Http\Requests\Conselho;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ConselhoCreateRequest extends FormRequest
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
            'CONSELHO_TIPO' => ['required'],
            'CONSELHO_SIGLA' => ['required','unique:CONSELHO'],
            'CONSELHO_NOME' => ['required','unique:CONSELHO'],
            'CONSELHO_ATIVO' => ['required','integer'],
        ];
    }

    public function attributes() {
        return [
            "CONSELHO_ID" => "<strong>CONSELHO ID</strong>",
            "CONSELHO_TIPO" => "<strong>TIPO DE CONSELHO</strong>",
            "CONSELHO_SIGLA" => "<strong>SIGLA</strong>",
            "CONSELHO_NOME" => "<strong>NOME</strong>",
            "CONSELHO_ATIVO" => "<strong>ATIVO</strong>",
        ];
    }
}
