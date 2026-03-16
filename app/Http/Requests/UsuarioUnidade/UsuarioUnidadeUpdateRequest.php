<?php

namespace App\Http\Requests\UsuarioUnidade;

class UsuarioUnidadeUpdateRequest extends UsuarioUnidadeCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["USUARIO_UNIDADE_ID"] = ["required","integer"];
        $regras["unidades"] = ["nullable"];
        return $regras;
    }
}
