<?php

namespace App\Http\Requests\Cargo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CargoCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check();
    }

    public function rules()
    {
        return [
            "CARGO_NOME" => ["required","unique:CARGO","min:3"],
            "CARGO_ESCOLARIDADE" => ["required"],
            "CARGO_SIGLA" => ["required","min:3","max:10","unique:CARGO"],
        ];
    }

    public function attributes()
    {
        return[
            "CARGO_ID" => "<b>CARGO ID</b>",
            "CARGO_NOME" => "<b>DESCRIÇÃO</b>",
            "CARGO_ESCOLARIDADE" => "<b>ESCOLARIDADE</b>",
            "CARGO_SIGLA" => "<b>SIGLA</b>",
        ];
    }
}
