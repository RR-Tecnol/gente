<?php

namespace App\Http\Requests\DetalheEscala;

use App\Rules\Escala\ChecarEscalaDeferida;
use App\Rules\Escala\NaoEditarEscalaPassadaRule;
use Illuminate\Foundation\Http\FormRequest;

class DetalheEscalaDeleteRequest extends DetalheEscalaCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "ESCALA_ID" => ["required","integer",new NaoEditarEscalaPassadaRule, new ChecarEscalaDeferida],
            "DETALHE_ESCALA_ID" => ['required'],
        ];
    }
}
