<?php

namespace App\Http\Requests\Usuario;

use Illuminate\Validation\Rule;

class UsuarioUpdateRequest extends UsuarioCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('USUARIO')->ignore($this->request->all()["USUARIO_ID"],"USUARIO_ID");
        return [
            "USUARIO_ID" => ["required","integer"],
            "USUARIO_LOGIN" => ["required",$uniqueIgnoreId,"max:50"],
            "USUARIO_SENHA" => ["required_unless:USUARIO_SENHA_CONFIRMATION,","same:USUARIO_SENHA_CONFIRMATION","max:32"],
            "USUARIO_NOME" => ["required",$uniqueIgnoreId,"max:255"],
            "USUARIO_CPF" => ["required",$uniqueIgnoreId,"size:11",'cpf'],
            "USUARIO_EMAIL" => ["required",$uniqueIgnoreId,"max:50"],
            "USUARIO_ATIVO" => ["required","integer"],
//            "USUARIO_VIGENCIA" => ["date"],
            "USUARIO_SENHA_CONFIRMATION" => ["required_unless:USUARIO_SENHA,"],
        ];
    }

}
