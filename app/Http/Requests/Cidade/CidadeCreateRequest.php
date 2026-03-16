<?php

namespace App\Http\Requests\Cidade;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CidadeCreateRequest extends FormRequest
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
            'CIDADE_IBGE' => ['required'],
            'CIDADE_NOME' => ['required'],
            'UF_ID' => ['required'],
            // 'CIDADE_UF' => ['required'],
        ];
    }

    public function attributes()
    {
        return [
            'CIDADE_ID' => '<b>ID</b>',
            'CIDADE_IBGE' => '<b>IBGE</b>',
            'CIDADE_NOME' => '<b>NOME DA CIDADE</b>',
            'UF_ID' => '<b>UF</b>',
            'CIDADE_UF' => '<b>CIDADE_UF</b>',
        ];
    }
}
