<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PessoaConselhoDeleteRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "PESSOA_CONSELHO_ID" => ["required"],
            "PESSOA_ID"     => ["required"],
        ];
    }

    public function attributes() {
        return [
            "PESSOA_ID"     => "<b>PESSOA</b>",
            "CONSELHO_ID"   => "<b>CONSELHO</b>",
        ];
    }
}
