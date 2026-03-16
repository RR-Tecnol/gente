<?php

namespace App\Http\Requests\PessoaConselho;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PessoaConselhoUpdateRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "PESSOA_CONSELHO_ID"        => ["required"],
            "PESSOA_ID"                 => ["required"],
            "CONSELHO_ID"               => ["required"],
            "UF_ID"                     => ["required"],
            "PESSOA_CONSELHO_NUMERO"    => [
                "required",
                "numeric",
                Rule::unique("PESSOA_CONSELHO")->where(function (Builder $query) {
                    $query
                        ->where("CONSELHO_ID", $this->input("CONSELHO_ID"))
                        ->where("UF_ID", $this->input("UF_ID"))
                        ->where("PESSOA_CONSELHO_NUMERO", $this->input("PESSOA_CONSELHO_NUMERO"))
                        ->where("PESSOA_CONSELHO_ID", "!=", $this->input("PESSOA_CONSELHO_ID"));
                })
            ],
        ];
    }

    public function attributes() {
        return [
            "PESSOA_CONSELHO_ID"     => "<b>ID</b>",
            "PESSOA_ID"              => "<b>PESSOA</b>",
            "CONSELHO_ID"            => "<b>CONSELHO</b>",
            "UF_ID"                  => "<b>UF</b>",
            "PESSOA_CONSELHO_NUMERO" => "<b>NÚMERO DO CONSELHO</b>",
        ];
    }

    public function messages() {
        return [
            "PESSOA_CONSELHO_NUMERO.unique" => "O número do conselho já está cadastrado no mesmo tipo e UF"
        ];
    }
}
