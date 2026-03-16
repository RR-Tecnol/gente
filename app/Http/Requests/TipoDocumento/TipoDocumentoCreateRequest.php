<?php

namespace App\Http\Requests\TipoDocumento;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class TipoDocumentoCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "TIPO_DOCUMENTO_DESCRICAO" => ["required","unique:TIPO_DOCUMENTO","min:2"]
        ];
    }

    public function attributes()
    {
        return[
            "TIPO_DOCUMENTO_ID" => "<b>TIPO DOCUMENTO ID</b>",
            "TIPO_DOCUMENTO_DESCRICAO" => "<b>DESCRIÇÃO</b>"
        ];
    }

}
