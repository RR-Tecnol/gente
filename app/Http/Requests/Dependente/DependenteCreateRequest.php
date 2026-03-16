<?php

namespace App\Http\Requests\Dependente;

use App\Rules\DependenteCpfUnicoRule;
use App\Rules\DependenteNaoPodeSerPropriaPessoaRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DependenteCreateRequest extends FormRequest {
    public function authorize() {
        return Auth::check();
    }

    public function rules() {
        return [
            "DEPENDENTE_NOME"       => ["required", "max:150"],
            "DEPENDENTE_SEXO"       => ["required"],
            "DEPENDENTE_CPF"        => [
                "bail",
                "nullable",
                "max:11",
                "cpf",
                "unique:DEPENDENTE,DEPENDENTE_CPF"
            ],
            'DEPENDENTE_NASCIMENTO' => ['required', 'date'],
            'DEPENDENTE_TIPO'       => ['required', 'integer'],
            'DEPENDENTE_DT_INICIO'  => ['required', 'date', 'after_or_equal:DEPENDENTE_NASCIMENTO'],
            'DEPENDENTE_DT_FIM'     => ['nullable', 'date', 'after:DEPENDENTE_DT_INICIO'],
            'DEPENDENTE_TIPO_FIM'   => ['integer', 'required_with:DEPENDENTE_DT_FIM', 'nullable'],

            'PESSOA_ID'             => ['required', 'integer'],
        ];
    }

    public function attributes() {
        return [
            "DEPENDENTE_NOME"        => "<b>NOME</b>",
            "DEPENDENTE_SEXO"        => "<b>SEXO</b>",
            "DEPENDENTE_CPF"         => "<b>CPF</b>",
            "DEPENDENTE_NASCIMENTO"  => "<b>DATA DE NASCIMENTO</b>",
            "DEPENDENTE_TIPO"        => "<b>GRAU DE PARENTESCO</b>",
            "DEPENDENTE_DT_INICIO"   => "<b>INÍCIO</b>",
            "DEPENDENTE_DT_FIM"      => "<b>FIM</b>",
            "DEPENDENTE_TIPO_FIM"    => "<b>TIPO DE FIM</b>",

            "PESSOA_ID"              => "<b>PESSOA</b>",
        ];
    }

    public function messages() {
        return [
            "DEPENDENTE_PESSOA_ID.unique" => "A <b>PESSOA</b> selecionada já é um dependente da pessoa original"
        ];
    }
}
