<?php

namespace App\Http\Requests\AnexoFerias;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AnexoFeriasCreateRequest extends FormRequest
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
            "FERIAS_ID" => ['required',"integer"],
            "ANEXO_FERIAS_ARQUIVO" => ['required', 'file', 'max:1000', 'mimes:doc,docx,xls,xlsx,pdf,txt'],
            "ANEXO_FERIAS_DESCRICAO" => ['required', "min:3"],
        ];
    }

    public function attributes()
    {
        return[
            "ANEXO_FERIAS_ID" => "<b>ID ANEXO FÉRIAS</b>",
            "FERIAS_ID" => "<b>ID DA FÉRIAS</b>",
            "ANEXO_FERIAS_ARQUIVO" => "<b>ARQUIVO</b>",
            "ANEXO_FERIAS_DESCRICAO" => "<b>DESCRIÇÃO</b>",
        ];
    }

    public function messages()
    {
        return[
            'ANEXO_FERIAS_ARQUIVO.max' => 'O campo :attributes não pode ser superior a 1 megabytes.'
        ];
    }
}
