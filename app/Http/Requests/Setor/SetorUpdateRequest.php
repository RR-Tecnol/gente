<?php

namespace App\Http\Requests\Setor;

class SetorUpdateRequest extends SetorCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
       $regras = parent::rules();
       $regras["SETOR_ID"] = ["required","integer"];
       return $regras;
    }
}
