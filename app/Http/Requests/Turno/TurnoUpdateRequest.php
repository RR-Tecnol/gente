<?php

namespace App\Http\Requests\Turno;

use Illuminate\Validation\Rule;

class TurnoUpdateRequest extends TurnoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('TURNO')->ignore($this->request->all()["TURNO_ID"],"TURNO_ID");
        return [
            "TURNO_ID" => ["required","integer"],
            "TURNO_DESCRICAO" => ["required",$uniqueIgnoreId],
            "TURNO_SIGLA" => ["required","min:3","max:7",$uniqueIgnoreId],
        ];
    }

}
