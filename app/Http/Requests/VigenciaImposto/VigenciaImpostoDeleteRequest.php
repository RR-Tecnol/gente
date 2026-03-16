<?php

namespace App\Http\Requests\VigenciaImposto;

use Illuminate\Foundation\Http\FormRequest;

class VigenciaImpostoDeleteRequest extends VigenciaImpostoCreateRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'VIGENCIA_IMPOSTO_ID' => ['required']
        ];
    }
}
