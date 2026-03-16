<?php

namespace App\Http\Requests\Funcionario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class FuncionarioSearchRequest extends FormRequest
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
            "FUNCIONARIO_PESSOA" => ["min:1"]
        ];
    }


    public function attributes()
    {
        return [
            "FUNCIONARIO_PESSOA" => "<b>PESQUISA FUNCIONÁRIO</b>",
        ];
    }
}
