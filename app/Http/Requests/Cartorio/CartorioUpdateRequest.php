<?php

namespace App\Http\Requests\Cartorio;

use Illuminate\Validation\Rule;

class CartorioUpdateRequest extends CartorioCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('CARTORIO')->ignore($this->request->all()["CARTORIO_ID"],"CARTORIO_ID");
        return [
            "CARTORIO_NOME" => ["required",$uniqueIgnoreId],
            "CIDADE_ID" => ["required"],
        ];
    }
}
