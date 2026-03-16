<?php

namespace App\Http\Requests\Feriado;

class FeriadoUpdateRequest extends FeriadoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["FERIADO_ID"] = ["required","integer"];
        return $regras;
    }
}
