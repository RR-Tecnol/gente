<?php

namespace App\Http\Requests\Escala;

use App\Rules\ChecarAcessoUsuarioSetor;
use App\Rules\Escala\ChecarPeriodoPosterior;
use App\Rules\Escala\ChecarRepeticaoSetorCompetencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class EscalaCreateRequest extends FormRequest
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
            "SETOR_ID" => ["required", "integer", new ChecarAcessoUsuarioSetor],
            "ESCALA_COMPETENCIA" => [
                "bail",
                "required", "size:7",
                new ChecarPeriodoPosterior,
                new ChecarRepeticaoSetorCompetencia($this->input('SETOR_ID'), $this->input('TIPO_ESCALA_ID'))
            ],
            "ESCALA_DESCRICAO" => ["required"],
            "TIPO_ESCALA_ID" => ["required", "integer"],
        ];
    }

    public function attributes()
    {
        return [
            "ESCALA_ID" => "<strong>ESCALA ID</strong>",
            "SETOR_ID" => "<strong>SETOR</strong>",
            "ESCALA_COMPETENCIA" => "<strong>PERÍODO</strong>",
            "ESCALA_DESCRICAO" => "<strong>DESCRIÇÃO</strong>",
            "TIPO_ESCALA_ID" => "<strong>TIPO DE ESCALA</strong>"
        ];
    }

    public function messages()
    {
        return [
            "ESCALA_COMPETENCIA.size" => "O formato do <b>PERÍODO (MM/AAAA)</b> está incompleto.",
        ];
    }
}
