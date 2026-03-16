<?php

namespace App\Http\Requests\Bairro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class BairroCreateRequest extends FormRequest
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
            'BAIRRO_NOME' => ['required'],
            'CIDADE_ID' => ['required'],
        ];
    }

    public function attributes()
    {
        return [
            'BAIRRO_ID' => '<b>ID</b>',
            'BAIRRO_NOME' => '<b>NOME</b>',
            'CIDADE_ID' => '<b>CIDADE</b>',
        ];
    }
}
