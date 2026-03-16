<?php

namespace App\Http\Requests\FimLotacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class FimLotacaoCreateRequest extends FormRequest
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
            "FIM_LOTACAO_DESCRICAO" => ['required',"unique:FIM_LOTACAO", "min:3"],
        ];
    }

    public function attributes()
    {
        return[
            "FIM_LOTACAO_ID" => "<b>FIM LOTAÇÃO ID</b>",
            "FIM_LOTACAO_DESCRICAO" => "<b>DESCRIÇÃO</b>",
        ];
    }
}
