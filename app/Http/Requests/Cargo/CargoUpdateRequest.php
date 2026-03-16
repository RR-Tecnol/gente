<?php

namespace App\Http\Requests\Cargo;

use Illuminate\Validation\Rule;

class CargoUpdateRequest extends CargoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('CARGO')->ignore($this->request->all()["CARGO_ID"],"CARGO_ID");
        return [
            "CARGO_NOME" => ["required",$uniqueIgnoreId,"min:3"],
            "CARGO_ESCOLARIDADE" => ["required"],
            "CARGO_SIGLA" => ["required","min:3","max:10",$uniqueIgnoreId],
        ];
    }

}
