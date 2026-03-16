<?php

namespace App\Http\Requests\Banco;

use Illuminate\Validation\Rule;

class BancoUpdateRequest extends BancoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('BANCO')->ignore($this->request->all()["BANCO_ID"],"BANCO_ID");
        return [
            'BANCO_ID' => ["required","integer"],
            'BANCO_CODIGO' => ['required',$uniqueIgnoreId],
            'BANCO_NOME' => ['required',$uniqueIgnoreId]
        ];
    }
}
