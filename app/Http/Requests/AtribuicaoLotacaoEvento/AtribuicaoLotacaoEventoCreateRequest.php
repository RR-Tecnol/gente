<?php

namespace App\Http\Requests\AtribuicaoLotacaoEvento;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AtribuicaoLotacaoEventoCreateRequest extends FormRequest
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
            "lotacoes" => ['required'],
            "eventos" => ['required'],
            "lotacoes.*" => ["required"],
//            "eventos.*.LOTACAO_EVENTO_INICIO" => ["required"],
//            "eventos.*.LOTACAO_EVENTO_FIM" => ["nullable", "gt:LOTACAO_EVENTO_INICIO"]
        ];
    }

    public function attributes()
    {
        return[
            "lotacoes" => "<b>LOTAÇÃO</b>",
            "LOTACAO_EVENTO_ID" => "<b>LOTAÇÃO EVENTO</b>",
            "LOTACAO_ID" => "<b>LOTAÇÃO</b>",
            "EVENTO_ID" => "<b>EVENTO</b>",
            "LOTACAO_EVENTO_INFO" => "<b>INFORMAÇÕES ADICIONAIS</b>",
            "LOTACAO_EVENTO_INICIO" => "<b>VINGÊNCIA INÍCIO</b>",
            "LOTACAO_EVENTO_FIM" => "<b>VINGÊNCIA FIM</b>",
            "LOTACAO_EVENTO_VALOR" => "<b>VALOR</b>",
            "eventos" => "<b>EVENTO</b>",
        ];
    }

    public function messages()
    {
        return [
            "eventos.*.LOTACAO_EVENTO_FIM.gt" => 'O campo <strong>VINGÊNCIA FIM</strong> deve ser maior que a <strong>VINGÊNCIA INÍCIO</strong>.'
        ];
    }
}
