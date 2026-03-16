<?php

namespace App\Http\Requests\UsuarioPerfil;

use App\Rules\Usuario\UnicoUsuarioPerfil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UsuarioPerfilCreateRequest extends FormRequest
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
            "USUARIO_ID" => ["required","integer"],
            "PERFIL_ID" => ["required","integer", new UnicoUsuarioPerfil($this->request->all()['USUARIO_ID'])],
            "USUARIO_PERFIL_ATIVO" => ["required","integer"]
        ];
    }

    public function attributes()
    {
        return [
            "USUARIO_PERFIL_ID" => "<b>USUÁRIO PERFIL ID</b>",
            "USUARIO_ID" => "<b>USUÁRIO</b>",
            "PERFIL_ID" => "<b>PERFIL</b>",
            "USUARIO_PERFIL_ATIVO" => "<b>ATIVO</b>",
        ];
    }
}
