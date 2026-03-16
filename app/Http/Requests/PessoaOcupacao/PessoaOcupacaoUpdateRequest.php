<?php

namespace App\Http\Requests\PessoaOcupacao;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PessoaOcupacaoUpdateRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "PESSOA_OCUPACAO_ID"        => ["required"],
            "PESSOA_ID"                 => ["required"],
            "OCUPACAO_ID"               => [
                "required",
                Rule::unique("PESSOA_OCUPACAO")->where(function (Builder $query) {
                    $query
                        ->where("PESSOA_ID", $this->input("PESSOA_ID"))
                        ->where("OCUPACAO_ID", $this->input("OCUPACAO_ID"))
                        ->where("PESSOA_OCUPACAO_ID", "!=", $this->input("PESSOA_OCUPACAO_ID"));
                })
            ],
        ];
    }

    public function attributes() {
        return [
            "PESSOA_OCUPACAO_ID"        => "<b></b>",
            "PESSOA_ID"                 => "<b>PESSOA</b>",
            "OCUPACAO_ID"               => "<b>OCUPAÇÃO</b>",
            "PESSOA_OCUPACAO_PRINCIPAL" => "<b>PRINCIPAL</b>",
        ];
    }

    public function messages() {
        return [
            "OCUPACAO_ID.unique" => "Ocupação já adicionada"
        ];
    }
}
