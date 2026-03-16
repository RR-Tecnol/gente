<?php

namespace App\Http\Requests\Bairro;

use Illuminate\Foundation\Http\FormRequest;

class BairroUpdateRequest extends BairroCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras['BAIRRO_ID'] = ['required'];
        return $regras;
    }
}
