<?php

namespace App\Http\Requests\TipoAlerta;

use Illuminate\Validation\Rule;

class TipoAlertaUpdateRequest extends TipoAlertaCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('TIPO_ALERTA')->ignore($this->request->all()["TIPO_ALERTA_ID"],"TIPO_ALERTA_ID");
        $regras = parent::rules();
        $regras["TIPO_ALERTA_ID"] = ["required","integer"];
        $regras["TIPO_ALERTA_DESCRICAO"] = ["required",$uniqueIgnoreId,"min:3"];
        return $regras;
    }

}
