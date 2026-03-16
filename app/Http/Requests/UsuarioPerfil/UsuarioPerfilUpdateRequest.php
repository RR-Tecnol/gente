<?php

namespace App\Http\Requests\UsuarioPerfil;

class UsuarioPerfilUpdateRequest extends UsuarioPerfilCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras = parent::rules();
        $regras["USUARIO_PERFIL_ID"] = ["required","integer"];
        $regras["PERFIL_ID"] = ["required","integer"];
        return $regras;
    }
}
