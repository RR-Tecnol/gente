<?php

namespace App\Http\Requests\AnexoFerias;

class AnexoFeriasUpdateRequest extends AnexoFeriasCreateRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["ANEXO_FERIAS_ID"] = ["required","integer"];
        return $regras;
    }
}
