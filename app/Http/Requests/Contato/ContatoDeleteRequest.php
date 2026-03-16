<?php

namespace App\Http\Requests\Contato;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ContatoDeleteRequest extends FormRequest {
    public function authorize() {
        return Auth::check();
    }

    public function rules() {
        return [
            "CONTATO_ID"        => ["required", "integer"],
            "PESSOA_ID"         => ["required", "integer"],
        ];
    }

    public function attributes() {
        return [
            "CONTATO_ID"        => "<b>ID</b>",
            "PESSOA_ID"         => "<b>PESSOA</b>",
        ];
    }
}
