<?php

namespace App\Http\Requests\UsuarioUnidade;

use App\Rules\Usuario\UnicoUsuarioUnidade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UsuarioUnidadeCreateRequest extends FormRequest
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
//        dd($this->request->all());
        return [
//            "UNIDADE_ID" => ["required","integer", new UnicoUsuarioUnidade($this->request->all()['USUARIO_ID'])],
            "USUARIO_ID" => ["required","integer"],
            "USUARIO_UNIDADE_FISCAL" => ["required","integer"],
            "USUARIO_UNIDADE_ATIVO" => ["required","integer"],
            "unidades" => ["required"],
            "unidades.*.UNIDADE_ID" => ["required","integer", new UnicoUsuarioUnidade($this->request->all()['USUARIO_ID'])],
        ];
    }

    public function attributes()
    {
        return[
            "USUARIO_UNIDADE_ID" => "<b>USUÁRIO UNIDADE ID</b>",
            "UNIDADE_ID" => "<b>UNIDADE</b>",
            "USUARIO_ID" => "<b>USUÁRIO</b>",
            "USUARIO_UNIDADE_FISCAL" => "<b>FISCAL</b>",
            "USUARIO_UNIDADE_ATIVO" => "<b>ATIVO</b>",
            "unidades" => "<b>UNIDADES</b>",
        ];
    }
}
