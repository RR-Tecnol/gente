<?php

namespace App\Http\Requests\Dependente;

use Illuminate\Foundation\Http\FormRequest;

class DependenteDeleteRequest extends DependenteCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $regras["DEPENDENTE_ID"] = ["required","integer"];
        return $regras;
    }
}
