<?php

namespace App\Http\Requests\uf;

use Illuminate\Foundation\Http\FormRequest;

class UfUpdateRequest extends UfRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras['UF_ID'] = ['required'];
        return $regras;
    }
}
