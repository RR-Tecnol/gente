<?php

namespace App\Http\Requests\Dossie;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class DossieCreateRequest extends FormRequest
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
            "LOTACAO_ID" => ["required","integer"],
            "DOSSIE_DT_OCORRENCIA" => ["required","date"],
        ];
    }

    public function attributes()
    {
        return[
            "DOSSIE_ID" => "<b>LOTAÇÃO</b>",
            "LOTACAO_ID" => "<b>LOTAÇÃO</b>",
            "DOSSIE_DT_OCORRENCIA" => "<b>DATA DE OCORRÊNCIA</b>",
        ];
    }

    public function messages()
    {
        return [
            "LOTACAO_ID.required" => "Selecione o <b>FUNCIONÁRIO</b>, em seguida, selecione uma de suas <b>LOTAÇÕES</b>.",
        ];
    }
}
