<?php

namespace App\Http\Requests\TabelaGenerica;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TabelaGenericaInserirTabelaRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
//            "TABELA_ID" => ["required"],
            "DESCRICAO" => [
                "required",
                Rule::unique("TABELA_GENERICA")
                ->where("COLUNA_ID", 0)
                ->where("DESCRICAO", $this->post("DESCRICAO"))
            ],
        ];
    }

    public function attributes() {
        return [
            "TABELA_ID" => "<b>TABELA_ID</b>",
            "DESCRICAO" => "<b>NOME DA TABELA</b>",
        ];
    }
}
