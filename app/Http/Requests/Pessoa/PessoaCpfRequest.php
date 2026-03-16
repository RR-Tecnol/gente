<?php

namespace App\Http\Requests\Pessoa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PessoaCpfRequest extends FormRequest
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
            'cpf' => [
                'required',
                'string',
                'exists:PESSOA,PESSOA_CPF_NUMERO',
            ],
        ];
    }

    public function messages()
    {
        return [
            'cpf.required' => 'O CPF é obrigatório.',
            'cpf.exists' => 'Nenhuma pessoa foi encontrada com o CPF informado.',
        ];
    }
}
