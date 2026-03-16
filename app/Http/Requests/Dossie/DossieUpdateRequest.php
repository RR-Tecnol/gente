<?php

namespace App\Http\Requests\Dossie;

class DossieUpdateRequest extends DossieCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["DOSSIE_ID"] = ["required","integer"];
        return $regras;
    }
}
