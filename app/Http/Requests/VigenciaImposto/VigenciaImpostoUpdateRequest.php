<?php

namespace App\Http\Requests\VigenciaImposto;

use Illuminate\Foundation\Http\FormRequest;

class VigenciaImpostoUpdateRequest extends VigenciaImpostoCreateRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras['VIGENCIA_IMPOSTO_ID'] = ['required'];
        return $regras;
    }
}
