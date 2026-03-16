<?php

namespace App\Http\Requests\Usuario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UsuarioCreateRequest extends FormRequest
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
            "USUARIO_LOGIN" => ["required","unique:USUARIO","max:50"],
            "USUARIO_SENHA" => ["required","same:USUARIO_SENHA_CONFIRMATION","max:32"],
            "USUARIO_NOME" => ["required","unique:USUARIO","max:255"],
            "USUARIO_CPF" => ["required","unique:USUARIO","size:11",'cpf'],
            "USUARIO_EMAIL" => ["required","unique:USUARIO","email","max:50"],
            "USUARIO_ATIVO" => ["required","integer"],
//            "USUARIO_VIGENCIA" => ["date"],
            "USUARIO_SENHA_CONFIRMATION" => ["required"]
        ];
    }

    public function attributes() {
        return [
            "USUARIO_ID" => "<b>USUARIO ID</b>",
            "USUARIO_LOGIN" => "<b>LOGIN</b>",
            "USUARIO_SENHA" => "<b>SENHA</b>",
            "USUARIO_NOME"  => "<b>NOME</b>",
            "USUARIO_CPF"  => "<b>CPF</b>",
            "USUARIO_EMAIL" => "<b>EMAIL</b>",
            "USUARIO_ATIVO" => "<b>ATIVO</b>",
//            "USUARIO_VIGENCIA" => "<b>VINGÊNCIA</b>",
            "USUARIO_SENHA_CONFIRMATION" => "<b>CONFIRMAR SENHA</b>",
        ];
    }

    public function messages()
    {
        return [
          "USUARIO_CPF.cpf" => 'O campo :attribute é inválido.'
        ];
    }
}
