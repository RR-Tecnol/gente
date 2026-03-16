<?php

namespace App\Http\Requests\Escala;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class EscalaIdRequest extends FormRequest
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
            'escalaId' => [
                'required',
                'string',
                'exists:ESCALA,ESCALA_ID',
            ],
        ];
    }

    public function messages()
    {
        return [
            'escalaId.required' => 'O ID da Escala é obrigatório.',
            'escalaId.exists' => 'Nenhuma escala foi encontrada com o ID informado.',
        ];
    }
}
