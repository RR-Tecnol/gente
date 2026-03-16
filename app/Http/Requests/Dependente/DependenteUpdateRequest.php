<?php

namespace App\Http\Requests\Dependente;

use App\Rules\DependenteCpfUnicoRule;
use App\Rules\DependenteCpfUnicoUpdateRule;
use App\Rules\DependenteNaoPodeSerPropriaPessoaRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DependenteUpdateRequest extends DependenteCreateRequest {
    public function rules() {
        $regras = parent::rules();
        $regras["DEPENDENTE_ID"] = ["required", "integer"];
        $regras["DEPENDENTE_CPF"] = [
            "bail",
            "nullable",
            "cpf",
            Rule::unique('DEPENDENTE')->ignore($this->input("DEPENDENTE_ID"), "DEPENDENTE_ID")
        ];
        return $regras;
    }
}
