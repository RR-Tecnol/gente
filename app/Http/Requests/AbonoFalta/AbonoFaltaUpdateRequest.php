<?php

namespace App\Http\Requests\AbonoFalta;

use Illuminate\Validation\Rule;

class AbonoFaltaUpdateRequest extends AbonoFaltaCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('ABONO_FALTA')->ignore($this->request->all()["DETALHE_ESCALA_ITEM_ID"],"DETALHE_ESCALA_ITEM_ID");
        $regras = parent::rules();
        $regras["DETALHE_ESCALA_ITEM_ID"] = ["required",$uniqueIgnoreId,"integer"];
        return $regras;
    }
}
