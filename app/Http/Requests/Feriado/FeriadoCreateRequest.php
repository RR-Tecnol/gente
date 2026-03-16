<?php

namespace App\Http\Requests\Feriado;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class FeriadoCreateRequest extends FormRequest
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
            'FERIADO_DATA' => ['required','date','after_or_equal:' .date('Y-01-01')],
            'FERIADO_DESCRICAO' => ['required'],
            'FERIADO_TIPO' => ['required','integer'],
            'FERIADO_ATIVO' => ['required','integer']
        ];
    }

    public function attributes() {
        return [
            "FERIADO_ID" => "<strong>FERIADO ID</strong>",
            "FERIADO_DATA" => "<strong>DATA</strong>",
            "FERIADO_DESCRICAO" => "<strong>DESCRICAO</strong>",
            "FERIADO_TIPO" => "<strong>TIPO DE FERIADO</strong>",
            "FERIADO_ATIVO" => "<strong>ATIVO</strong>",
        ];
    }

    public function messages()
    {
        return [
            "FERIADO_DATA.after_or_equal" => "O campo :attribute deve ser uma data com ano atual ou posterior.",
        ];
    }
}
