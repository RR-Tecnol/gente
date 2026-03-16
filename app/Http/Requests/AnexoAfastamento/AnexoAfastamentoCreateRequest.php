<?php

namespace App\Http\Requests\AnexoAfastamento;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AnexoAfastamentoCreateRequest extends FormRequest
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

    public function rules()
    {
        return [
            "AFASTAMENTO_ID" => ['required',"integer"],
            "ANEXO_AFASTAMENTO_ARQUIVO" => ['required', 'file', 'max:1000', 'mimes:doc,docx,xls,xlsx,pdf,txt'],
            "ANEXO_AFASTAMENTO_DESCRICAO" => ['required', "min:3"],
        ];
    }

    public function attributes()
    {
        return[
            "ANEXO_AFASTAMENTO_ID" => "<b>ID ANEXO AFASTAMENTO</b>",
            "AFASTAMENTO_ID" => "<b>ID DA AFASTAMENTO</b>",
            "ANEXO_AFASTAMENTO_ARQUIVO" => "<b>ARQUIVO</b>",
            "ANEXO_AFASTAMENTO_DESCRICAO" => "<b>DESCRIÇÃO</b>",
        ];
    }

    public function messages()
    {
        return[
            'ANEXO_AFASTAMENTO_ARQUIVO.max' => 'O campo :attributes não pode ser superior a 1 megabytes.',
        ];
    }
}
