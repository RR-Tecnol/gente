<?php

namespace App\Http\Requests\Depedente;

use Illuminate\Foundation\Http\FormRequest;

class DependenteDeleteRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            'DEPENDENTE_ID' => ['required'],
            'PESSOA_ID' => ['required']

        ];
    }
}
