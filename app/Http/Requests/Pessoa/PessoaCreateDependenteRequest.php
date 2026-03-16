<?php

namespace App\Http\Requests\Pessoa;

use App\MyLibs\TipoDocumentoEnum;
use App\Rules\DependenteCpfUnicoRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PessoaCreateDependenteRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "PESSOA_NOME"           => ["required"],
            "PESSOA_DATA_NASCIMENTO"=> ["required"],
            "PESSOA_SEXO"           => ["required"],
            "CPF"                   => [
                "bail",
                "nullable",
                'cpf',
                new DependenteCpfUnicoRule()
            ],
        ];
    }

    public function attributes() {
        return [
            "PESSOA_NOME"           => "<b>NOME</b>",
            "PESSOA_DATA_NASCIMENTO"=> "<b>DATA DE NASCIMENTO</b>",
            "PESSOA_SEXO"           => "<b>SEXO</b>",
            "CPF"                   => "<b>CPF</b>",
        ];
    }
}
