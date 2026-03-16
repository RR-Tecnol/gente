<?php

namespace App\Http\Requests\Escala;

use App\Rules\ChecarAcessoUsuarioSetor;
use App\Rules\Escala\ChecarEscalaDeferida;

class EscalaUpdateRequest extends EscalaCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // $regras = parent::rules();
        $regras["ESCALA_ID"] = ["required","integer", new ChecarEscalaDeferida];
        $regras["SETOR_ID"] = ["required","integer", new ChecarAcessoUsuarioSetor];
        return $regras;
    }
}
