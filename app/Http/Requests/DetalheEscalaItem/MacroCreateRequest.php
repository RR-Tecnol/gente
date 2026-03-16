<?php

namespace App\Http\Requests\DetalheEscalaItem;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class MacroCreateRequest extends FormRequest
{
    public function authorize()
    {
        return Auth::check();
    }

    public function rules()
    {
        $rules = [
            "detalhe_escala_id" => ["required", "integer"],
            "tipo" => ["required", "integer"],
            "turno_id" => ["required", "integer"],
        ];

        // Condicionais
        if ($this->input('tipo') == 2) {
            $rules['turno_sabado_id'] = ['required', 'integer'];
        }

        if ($this->input('tipo') == 3) {
            $rules['intervalo'] = ['required'];
            $rules['dataSelecionada'] = ['required', 'date'];
        }

        return $rules;
    }

    public function attributes()
    {
        return [
            "detalhe_escala_id" => "<b>DETALHE ESCALA</b>",
            "tipo" => "<b>TIPO</b>",
            "turno_id" => "<b>TURNO</b>",
            "turno_sabado_id" => "<b>TURNO SÁBADO</b>",
            "intervalo" => "<b>INTERVALO</b>",
            "dataSelecionada" => "<b>DATA</b>",
        ];
    }

    public function messages()
    {
        return [
            'dataSelecionada.required' => 'Selecione uma <b>DATA</b> no calendário.',
            'dataSelecionada.date' => 'Selecione uma <b>DATA</b> válida no calendário.',
        ];
    }
}
