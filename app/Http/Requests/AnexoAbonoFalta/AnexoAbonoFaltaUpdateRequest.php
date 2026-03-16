<?php

namespace App\Http\Requests\AnexoAbonoFalta;

class AnexoAbonoFaltaUpdateRequest extends AnexoAbonoFaltaCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["ANEXO_ABONO_FALTA_ID"] = ["required","integer"];
        return $regras;
    }
}
