<?php

namespace App\Http\Requests\TipoAlerta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class TipoAlertaCreateRequest extends FormRequest
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
            "TIPO_ALERTA_DESCRICAO" => ["required",'unique:TIPO_ALERTA',"min:3"],
            "TIPO_ALERTA_VISIVEL" => ["required","integer"],
            "TIPO_ALERTA_ATIVO" => ["required","integer"]
        ];
    }

    public function attributes()
    {
        return[
            "TIPO_ALERTA_ID" => "<b>TIPO ALERTA ID</b>",
            "TIPO_ALERTA_DESCRICAO" => "<b>DESCRIÇÃO</b>",
            "TIPO_ALERTA_VISIVEL" => "<b>VISÍVEL</b>",
            "TIPO_ALERTA_ATIVO" => "<b>ATIVO</b>"
        ];
    }
}
