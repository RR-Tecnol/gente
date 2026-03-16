<?php

namespace App\Http\Requests\Documento;

use App\Rules\Documento\CpfValidoRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentoUpdateRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "PESSOA_ID"         => ['required', "integer"],
            "DOCUMENTO_ID"      => ["required", "integer"],
            "TIPO_DOCUMENTO_ID" => [
                "bail",
                "required",
                "integer",
                Rule::unique("DOCUMENTO")->where(function ($q) {
                    $q->where("PESSOA_ID", $this->input("PESSOA_ID"))
                      ->where("TIPO_DOCUMENTO_ID", $this->input("TIPO_DOCUMENTO_ID"))
                      ->where("DOCUMENTO_ID", "!=", $this->input("DOCUMENTO_ID"));
                })
            ],
            "DOCUMENTO_NUMERO"  => [
                "bail",
                "required",
                "min:3",
                // new CpfValidoRule($this->input("TIPO_DOCUMENTO_ID")),
                Rule::unique("DOCUMENTO")->where(function (Builder $query) {
                    $query
                        ->where("TIPO_DOCUMENTO_ID", $this->input("TIPO_DOCUMENTO_ID"))
                        ->where("DOCUMENTO_NUMERO", $this->input("DOCUMENTO_NUMERO"))
                        ->where("DOCUMENTO_ID", "!=", $this->input("DOCUMENTO_ID"));
                }),
                // Validação específica para PIS/PASEP/NIT (TIPO_DOCUMENTO_ID = 1)
                function ($attribute, $value, $fail) {
                    if ($this->post("TIPO_DOCUMENTO_ID") == 17 && strlen($value) != 11) {
                        $fail("O <b>NÚMERO DO DOCUMENTO</b> do PIS/PASEP/NIT deve ter exatamente 11 caracteres.");
                    }
                }
            ],
        ];
    }

    public function attributes() {
        return [
            "DOCUMENTO_ID" => "<b>DOCUMENTO ID</b>",
            "DOCUMENTO_NUMERO" => "<b>NÚMERO DO DOCUMENTO</b>",
            "TIPO_DOCUMENTO_ID" => "<b>TIPO DO DOCUMENTO</b>",
            "PESSOA_ID" => "<b>PESSOA ID</b>"
        ];
    }

    public function messages() {
        return [
            "TIPO_DOCUMENTO_ID.unique" => "O <b>TIPO DE DOCUMENTO</b> já encontra-se cadastrado",
            "DOCUMENTO_NUMERO.unique"  => "O <b>DOCUMENTO</b> informado já encontra-se cadastrado",
        ];
    }
}
