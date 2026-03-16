<?php

namespace App\Http\Requests\TipoDocumento;

use Illuminate\Validation\Rule;

class TipoDocumentoUpdateRequest extends TipoDocumentoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('TIPO_DOCUMENTO')->ignore($this->request->all()["TIPO_DOCUMENTO_ID"],"TIPO_DOCUMENTO_ID");
        return [
            "TIPO_DOCUMENTO_ID" => ["required","integer"],
            "TIPO_DOCUMENTO_DESCRICAO" => ["required",$uniqueIgnoreId,"min:2"]
        ];
    }

}
