<?php

namespace App\Http\Requests\AtribuicaoConfig;

use App\Rules\AtribuicaoConfig\AtrbuicaoConfigUnico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AtribuicaoConfigCreateRequest extends FormRequest
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
            "ATRIBUICAO_CONFIG_CARGA_HORARIA" => ['required','integer'],
            "ATRIBUICAO_CONFIG_PORTE_UNIDADE" => ['required'],
            "ATRIBUICAO_ID" => ['required',new AtrbuicaoConfigUnico($this->request->all())],
            "ATRIBUICAO_CONFIG_ATIVA" => ['required',new AtrbuicaoConfigUnico($this->request->all())],
            'HIST_ATRIBUICAO_CONFIG_VALOR' => ['required'],
        ];
    }

    public function attributes()
    {
        return [
            'ATRIBUICAO_CONFIG_ID' => '<b>ATRIBUICAO CONFIG ID</b>',
            "ATRIBUICAO_CONFIG_CARGA_HORARIA" => '<b>CARGA HORARIA</b>',
            "ATRIBUICAO_CONFIG_PORTE_UNIDADE" => '<b>PORTE UNIDADE</b>',
            "ATRIBUICAO_ID" => '<b>ATRIBUICAO ID</b>',
            "ATRIBUICAO_CONFIG_ATIVA" => '<b>ATIVA</b>',
            'HIST_ATRIBUICAO_CONFIG_VALOR' => '<b>VALOR</b>'
        ];
    }

    public function messages()
    {
        return [
            "TIPO_ESCALA_ID.required_if" => "Obrigatório informar o Tipo de Escala quando a Carga Horária for Regime de Plantão."
        ];
    }
}
