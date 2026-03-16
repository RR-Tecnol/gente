<?php

namespace App\Http\Requests\Turno;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class TurnoCreateRequest extends FormRequest
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

    public function rules()
    {
        return [
            "TURNO_DESCRICAO" => ["required","unique:TURNO","min:3"],
            "TURNO_SIGLA" => ["required","min:1","max:6","unique:TURNO"],
            "TURNO_INTERVALO" => ["required"],
            "TURNO_HORA_INICIO" => ["required"],
            "TURNO_HORA_FIM" => ["required"],
        ];
    }

    public function attributes()
    {
        return[
            "TURNO_ID" => "<b>TURNO ID</b>",
            "TURNO_DESCRICAO" => "<b>DESCRIÇÃO</b>",
            "TURNO_SIGLA" => "<b>SIGLA</b>",
            "TURNO_INTERVALO" => "<b>INTERVALO</b>",
            "TURNO_HORA_INICIO" => "<b>INÍCIO DO TURNO</b>",
            "TURNO_HORA_FIM" => "<b>FIM DO TURNO</b>",
        ];
    }

}
