<?php

namespace App\Http\Requests\PessoaBanco;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PessoaBancoCreateRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "PESSOA_ID"                 => ["required"],
            "BANCO_ID"                  => ["required"],
            "PESSOA_BANCO_AGENCIA"      => ["required"],
            "PESSOA_BANCO_CONTA"        => ["required"],
            "PESSOA_BANCO_TIPO_CONTA"   => [
                "bail",
                "required",
                Rule::unique("PESSOA_BANCO")->where(function ($q) {
                    $q->where("BANCO_ID", $this->input("BANCO_ID"))
                        ->where("PESSOA_BANCO_AGENCIA", $this->input("PESSOA_BANCO_AGENCIA"))
                        ->where("PESSOA_BANCO_CONTA", $this->input("PESSOA_BANCO_CONTA"))
                        ->where("PESSOA_ID", $this->input("PESSOA_ID"));
                })
            ],
//            "PESSOA_BANCO_PIX"          => ["required"],
//            "PESSOA_BANCO_TIPO_PIX"     => ["required"],
        ];
    }

    public function attributes() {
        return [
            "PESSOA_ID"                 => "<b>PESSOA</b>",
            "BANCO_ID"                  => "<b>BANCO</b>",
            "PESSOA_BANCO_AGENCIA"      => "<b>AGÊNCIA</b>",
            "PESSOA_BANCO_CONTA"        => "<b>NÚMERO DA CONTA</b>",
            "PESSOA_BANCO_TIPO_CONTA"   => "<b>TIPO DE CONTA</b>",
            "PESSOA_BANCO_PIX"          => "<b>CHAVE PIX</b>",
            "PESSOA_BANCO_TIPO_PIX"     => "<b>TIPO DE CHAVE PIX</b>",
        ];
    }

    public function messages() {
        return [
            "PESSOA_BANCO_TIPO_CONTA.unique" => "Esta <b>CONTA</b> já está cadastrada"
        ];
    }
}
