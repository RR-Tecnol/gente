<?php

namespace App\Http\Requests\HistoricoParametro;

use App\Rules\HistoricoParametro\HistoricoParametroAtivoRule;
use App\Rules\HistoricoParametro\ValidarVingencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class HistoricoParametroCreateRequest extends FormRequest
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
            "PARAMETRO_FINANCEIRO_ID" => ['required'],
            "HISTORICO_PARAMETRO_TIPO" => ['required'],
            "HISTORICO_PARAMETRO_VALOR" => [
                'required',
                'numeric',
                ($this->input('HISTORICO_PARAMETRO_TIPO') == 2) ? 'max:100' : '',
            ],
            "HISTORICO_PARAMETRO_INICIO" => ['required', new HistoricoParametroAtivoRule($this->input('PARAMETRO_FINANCEIRO_ID'))],
            "HISTORICO_PARAMETRO_FIM" => ['nullable','after_or_equal:HISTORICO_PARAMETRO_INICIO', new HistoricoParametroAtivoRule($this->input('PARAMETRO_FINANCEIRO_ID'))],
        ];
    }

    public function attributes()
    {
        return [
            "HISTORICO_PARAMETRO_INICIO" => "<b>INÍCIO</b>",
            "HISTORICO_PARAMETRO_FIM" => "<b>FIM</b>",
            "HISTORICO_PARAMETRO_VALOR" => "<b>VALOR</b>",
            "HISTORICO_PARAMETRO_TIPO" => "<b>TIPO</b>",
            "PARAMETRO_FINANCEIRO_ID" => "<b>PARAMETRO FINANCEIRO ID</b>",
            "HISTORICO_PARAMETRO_ID" => "<b>HISTORICO ID</b>",
        ];
    }

}
