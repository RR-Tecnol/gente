<?php

namespace App\Http\Requests\Setor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SetorCreateRequest extends FormRequest
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
            "SETOR_NOME" => ["required","min:2"],
            "UNIDADE_ID" => ["required"],
            "SETOR_SIGLA" => ["required", "min:2","max:20",Rule::notIn(['NÚCLEO','NUCLEO']),],
        ];
    }

    public function attributes()
    {
        return[
            "SETOR_ID" => "<b>SETOR ID</b>",
            "SETOR_NOME" => "<b>NOME</b>",
            "SETOR_SIGLA" => "<b>SIGLA</b>",
            "UNIDADE_ID" => '<b>UNIDADE</b>'
        ];
    }
}
