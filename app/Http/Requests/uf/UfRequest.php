<?php

namespace App\Http\Requests\uf;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UfRequest extends FormRequest
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
            'UF_SIGLA' => ['required','max:2','string'],
            'UF_CODIGO' => ['required','max:2','string']
        ];
    }

    public function attributes()
    {
        return [
            'UF_ID' => '<b>ID</b>',
            'UF_SIGLA' => '<b>SIGLA</b>',
            'UF_CODIGO' => '<b>CODIGO</b>',
        ];
    }
}
