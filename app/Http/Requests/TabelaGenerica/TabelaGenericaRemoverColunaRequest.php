<?php

namespace App\Http\Requests\TabelaGenerica;

use Illuminate\Foundation\Http\FormRequest;

class TabelaGenericaRemoverColunaRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "TABELA_GENERICA_ID" => ["required"],
            "TABELA_ID" => ["required"],
            "COLUNA_ID" => ["required"],
        ];
    }

    public function attributes() {
        return [
            "TABELA_GENERICA_ID" => "<b>ID</b>",
            "TABELA_ID" => "<b>TABELA</b>",
            "COLUNA_ID" => "<b>COLUNA_ID</b>",
        ];
    }
}
