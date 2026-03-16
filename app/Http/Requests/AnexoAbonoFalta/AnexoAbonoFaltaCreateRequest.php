<?php

namespace App\Http\Requests\AnexoAbonoFalta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AnexoAbonoFaltaCreateRequest extends FormRequest
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
            "DETALHE_ESCALA_ITEM_ID" => ['required',"integer"],
            "ANEXO_ABONO_FALTA_ARQUIVO" => ['required', 'file', 'max:1000', 'mimes:doc,docx,xls,xlsx,pdf,txt'],
            "ANEXO_ABONO_FALTA_DESCRICAO" => ['required', "min:3"],
        ];
    }

    public function attributes()
    {
        return[
            "ANEXO_ABONO_FALTA_ID" => "<b>ID ANEXO ABONO FALTA</b>",
            "DETALHE_ESCALA_ITEM_ID" => "<b>ID DA DETALHE ESCALA ITEM</b>",
            "ANEXO_ABONO_FALTA_ARQUIVO" => "<b>ARQUIVO</b>",
            "ANEXO_ABONO_FALTA_DESCRICAO" => "<b>DESCRIÇÃO</b>",
        ];
    }

    public function messages()
    {
        return[
            'ANEXO_ABONO_FALTA_ARQUIVO.max' => 'O campo :attributes não pode ser superior a 1 megabytes.'
        ];
    }
}
