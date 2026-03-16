<?php

namespace App\Http\Requests\HistoricoEscala;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class HistoricoEscalaRequest extends FormRequest
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
            "ESCALA_ID" => ["required","integer"],
            "STATUS_ESCALA_ID" => ["required"],
            "HISTORICO_ESCALA_OBSERVACAO" => ["required"],
        ];
    }

    public function attributes()
    {
        return[
            "ESCALA_ID" => "<b>ESCALA</b>",
            "STATUS_ESCALA_ID" => "<b>STATUS ESCALA</b>",
            "HISTORICO_ESCALA_OBSERVACAO" => "<b>HISTÓRICO ESCALA OBSERVAÇÃO</b>",
        ];
    }
}
