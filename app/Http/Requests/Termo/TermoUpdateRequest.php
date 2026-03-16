<?php

namespace App\Http\Requests\Termo;

use Illuminate\Foundation\Http\FormRequest;

class TermoUpdateRequest extends TermoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'TERMO_NOME' => ['required'],
            
        ];
    }
}
