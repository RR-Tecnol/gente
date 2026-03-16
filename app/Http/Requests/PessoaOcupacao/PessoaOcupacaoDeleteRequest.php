<?php

namespace App\Http\Requests\PessoaOcupacao;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PessoaOcupacaoDeleteRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "PESSOA_OCUPACAO_ID" => ["required"],
            "PESSOA_ID"          => ["required"],
        ];
    }

    public function attributes() {
        return [
            "PESSOA_OCUPACAO_ID"        => "<b>ID</b>",
            "PESSOA_ID"                 => "<b>PESSOA</b>",
        ];
    }
}
