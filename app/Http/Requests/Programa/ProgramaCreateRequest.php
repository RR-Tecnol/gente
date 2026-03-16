<?php

namespace App\Http\Requests\Programa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ProgramaCreateRequest extends FormRequest
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
            "DESCRICAO" => ["required"],
            "CNPJ" => ["required"],
            "BANCO_ID" => ["required"],
            "FONTE_RECURSO_ID" => ["required"],
            "COD_CONVENIO" => ["required"],
            "CEP" => ["required"],
            "NUMERO" => ["required"],
            "ENDERECO" => ["required"],
            "AGENCIA" => ["required"],
            "AGENCIA_DV" => ["required"],
            "CONTA_CORRENTE" => ["required"],
            "CONTA_CORRENTE_DV" => ["required"],
            "COD_PROGRAMA" => ["required"],
        ];
    }

    public function attributes()
    {
        return [
            "PROGRAMA_ID"     => "<b>PROGRAMA ID</b>",
            "DESCRICAO"   => "<b>DESCRIÇÃO</b>",
            "CNPJ"         => "<b>CNPJ</b>",
            "BANCO_ID"         => "<b>BANCO</b>",
            "FONTE_RECURSO_ID"         => "<b>FONTE DE RECURSO</b>",
            "COD_CONVENIO"         => "<b>CÓDIGO DO CONVÊNIO</b>",
            "CEP"         => "<b>CEP</b>",
            "NUMERO"         => "<b>NÚMERO</b>",
            "ENDERECO"         => "<b>ENDEREÇO</b>",
            "AGENCIA"         => "<b>AGÊNCIA</b>",
            "AGENCIA_DV"         => "<b>AGÊNCIA DV</b>",
            "CONTA_CORRENTE"         => "<b>CONTA CORRENTE</b>",
            "CONTA_CORRENTE_DV"         => "<b>CONTA CORRENTE DV</b>",
            "COD_PROGRAMA"         => "<b>CÓDIGO DO PROGRAMA</b>",
        ];
    }
}
