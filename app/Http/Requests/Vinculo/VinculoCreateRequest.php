<?php

namespace App\Http\Requests\Vinculo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class VinculoCreateRequest extends FormRequest
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
            "VINCULO_DESCRICAO" => ["required",'unique:VINCULO'],
            "VINCULO_SIGLA" => ["required","max:7",'unique:VINCULO'],
            "VINCULO_ATIVO" => ["required","integer"],
        ];
    }

    public function attributes()
    {
        return[
            "VINCULO_ID" => "<b>VINCULO ID</b>",
            "VINCULO_DESCRICAO" => "<b>DESCRIÇÃO</b>",
            "VINCULO_SIGLA" => "<b>SIGLA</b>",
            "VINCULO_ATIVO" => "<b>ATIVO</b>",
        ];
    }
}
