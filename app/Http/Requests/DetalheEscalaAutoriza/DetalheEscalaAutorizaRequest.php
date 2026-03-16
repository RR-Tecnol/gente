<?php

namespace App\Http\Requests\DetalheEscalaAutoriza;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class DetalheEscalaAutorizaRequest extends FormRequest
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
            "DETALHE_ESCALA_ID" => ["required","integer"],
            "DETALHE_ESCALA_AUTORIZA_MOTIVO" => ["required"],
            "DETALHE_ESCALA_AUTORIZA_OBSERVACAO" => ["required", "min:3"],
        ];
    }

    public function attributes()
    {
        return[
            "DETALHE_ESCALA_ID" => "<b>DETALHE ESCALA</b>",
            "DETALHE_ESCALA_AUTORIZA_MOTIVO" => "<b>MOTIVO</b>",
            "DETALHE_ESCALA_AUTORIZA_OBSERVACAO" => "<b>OBSERVAÇÃO</b>",
        ];
    }
}
