<?php

namespace App\Http\Requests\Cartorio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CartorioCreateRequest extends FormRequest {
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        return [
            "CARTORIO_NOME" => ['required', 'unique:CARTORIO'],
            "CIDADE_ID" => ['required'],
        ];
    }

    public function attributes() {
        return [
            "CARTORIO_ID" => "<b>ID</b>",
            "CARTORIO_NOME" => "<b>NOME</b>",
            "CIDADE_ID" => "<b>CIDADE</b>",
        ];
    }
}
