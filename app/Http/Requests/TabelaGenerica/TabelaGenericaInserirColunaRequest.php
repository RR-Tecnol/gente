<?php

namespace App\Http\Requests\TabelaGenerica;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TabelaGenericaInserirColunaRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "TABELA_ID" => ["required"],
            "DESCRICAO" => [
                "required",
                Rule::unique("TABELA_GENERICA")
                    ->whereNot("TABELA_GENERICA_ID", $this->post("TABELA_GENERICA_ID"))
                    ->where("TABELA_ID", $this->post("TABELA_ID"))
                    ->where("DESCRICAO", $this->post("DESCRICAO"))
            ],
            "ATIVO" => ["required"],
        ];
    }

    public function attributes() {
        return [
            "TABELA_GENERICA_ID" => "<b>ID</b>",
            "TABELA_ID" => "<b>TABELA</b>",
            "DESCRICAO" => "<b>DESCRICAO</b>",
            "ATIVO" => "<b>ATIVO</b>",
        ];
    }

    public function messages() {
        return [
            "DESCRICAO.unique" => "O valor do campo <b>:attribute</b> já existe na tabela selecionada",
        ];
    }
}
