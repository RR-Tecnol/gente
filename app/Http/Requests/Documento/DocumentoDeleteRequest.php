<?php

namespace App\Http\Requests\Documento;

use Illuminate\Foundation\Http\FormRequest;

class DocumentoDeleteRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            "DOCUMENTO_ID" => ["required"],
        ];
    }

    public function attributes() {
        return [
            "DOCUMENTO_ID" => "DOCUMENTO ID"
        ];
    }
}
