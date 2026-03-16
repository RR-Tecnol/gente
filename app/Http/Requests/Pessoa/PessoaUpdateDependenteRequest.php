<?php

namespace App\Http\Requests\Pessoa;

use App\Rules\DependenteCpfUnicoRule;
use App\Rules\DependenteCpfUnicoUpdateRule;
use Illuminate\Foundation\Http\FormRequest;

class PessoaUpdateDependenteRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "PESSOA_ID"             => ["required"],
            "PESSOA_NOME"           => ["required"],
            "PESSOA_DATA_NASCIMENTO"=> ["required"],
            "PESSOA_SEXO"           => ["required"],
            "CPF"                   => [
                "bail",
                "nullable",
                'cpf',
                new DependenteCpfUnicoUpdateRule($this->input('PESSOA_ID')),
            ],
        ];
    }

    public function attributes() {
        return [
            "PESSOA_NOME"           => "<b>NOME</b>",
            "PESSOA_DATA_NASCIMENTO"=> "<b>SEXO</b>",
            "PESSOA_SEXO"           => "<b>DATA DE NASCIMENTO</b>",
            "CPF"                   => "<b>CPF</b>",
        ];
    }
}
