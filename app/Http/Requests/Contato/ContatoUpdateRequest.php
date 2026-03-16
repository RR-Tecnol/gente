<?php

namespace App\Http\Requests\Contato;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContatoUpdateRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "CONTATO_ID"        => ["required", "integer"],
            "CONTATO_TIPO"      => ["required", "integer"],
            "PESSOA_ID"         => ["required", "integer"],
            "CONTATO_CONTEUDO"  => [
                "required",
                "min:3",
                'max:50',
                Rule::unique("CONTATO")->where(function ($q) {
                    $q
                        ->where("CONTATO_TIPO", $this->input("CONTATO_TIPO"))
                        ->where("PESSOA_ID", $this->input("PESSOA_ID"))
                        ->where("CONTATO_CONTEUDO", $this->input("CONTATO_CONTEUDO"))
                        ->where("CONTATO_ID", "!=", $this->input("CONTATO_ID"));
                })
            ],
        ];
    }

    public function attributes() {
        return [
            "CONTATO_ID"        => "<b>ID</b>",
            "CONTATO_TIPO"      => "<b>TIPO DE CONTATO</b>",
            "PESSOA_ID"         => "<b>PESSOA</b>",
            "CONTATO_CONTEUDO"  => "<b>CONTATO</b>",
        ];
    }

    public function messages() {
        return [
            "CONTATO_CONTEUDO.unique" => "Contato já adicionado"
        ];
    }
}
