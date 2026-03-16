<?php

namespace App\Http\Requests\Funcionario;

use App\Rules\Funcionario\ChecarFuncionarioUsuario;
use Illuminate\Foundation\Http\FormRequest;

class FuncionarioDeleteRequest extends FuncionarioCreateRequest
{
    

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'id' => [ 'required', 'int' ]
        ];
    }
}
