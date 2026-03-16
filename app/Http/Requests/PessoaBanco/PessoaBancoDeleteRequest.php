<?php

namespace App\Http\Requests\PessoaBanco;

use Illuminate\Foundation\Http\FormRequest;

class PessoaBancoDeleteRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "PESSOA_BANCO_ID"           => ["required"],
            "PESSOA_ID"                 => ["required"],
        ];
    }

    public function attributes() {
        return [
            "PESSOA_ID"                 => "<b>PESSOA</b>",
            "BANCO_ID"                  => "<b>BANCO</b>",
        ];
    }
}
