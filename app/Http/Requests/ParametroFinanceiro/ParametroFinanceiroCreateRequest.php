<?php

namespace App\Http\Requests\ParametroFinanceiro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ParametroFinanceiroCreateRequest extends FormRequest
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
            'PARAMETRO_FINANCEIRO_NOME' => ['required','unique:PARAMETRO_FINANCEIRO']
        ];
    }

    public function attributes()
    {
        return[
            "PARAMETRO_FINANCEIRO_ID" => "<b>PARÂMTRO FINANCEIRO ID</b>",
            "PARAMETRO_FINANCEIRO_NOME" => "<b>DESCRIÇÃO</b>",
        ];
    }
}
