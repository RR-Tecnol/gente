<?php

namespace App\Http\Requests\Programa;

class ProgramaUpdateRequest extends ProgramaCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["PROGRAMA_ID"] = ["required", "integer"];
        return $regras;
    }
}
