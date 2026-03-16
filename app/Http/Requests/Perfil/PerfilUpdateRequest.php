<?php

namespace App\Http\Requests\Perfil;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerfilUpdateRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "PERFIL_ID"     => ["required", "integer"],
            "PERFIL_NOME"   => [
                "required",
                "max:20",
                Rule::unique("PERFIL")->where(function ($q) {
                    $q->where("PERFIL_NOME", $this->input("PERFIL_NOME"))
                        ->where("PERFIL_ID", "!=", $this->input("PERFIL_ID"));
                })
            ],
            "acessos" => [
                "required"
            ]
        ];
    }

    public function attributes() {
        return [
            "PERFIL_NOME" => "<b>NOME</b>",
            "acessos"   => "<b>ACESSO</b>"
        ];
    }

    public function messages() {
        return [
            "acessos.required" => "Nenhum :attribute informado"
        ];
    }
}
