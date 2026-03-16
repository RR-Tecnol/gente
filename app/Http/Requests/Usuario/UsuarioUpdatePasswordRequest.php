<?php

namespace App\Http\Requests\Usuario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UsuarioUpdatePasswordRequest extends FormRequest
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
            "USUARIO_SENHA" => ["required","same:USUARIO_SENHA_CONFIRMATION"],
            "USUARIO_SENHA_CONFIRMATION" => ["required"],
        ];
    }

    public function attributes() {
        return [
            "USUARIO_SENHA" => "<b>SENHA</b>",
            "USUARIO_SENHA_CONFIRMATION" => "<b>CONFIRMAR SENHA</b>",
        ];
    }
}
