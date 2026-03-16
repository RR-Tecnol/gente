<?php

namespace App\Http\Requests\Conselho;

use Illuminate\Validation\Rule;

class ConselhoUpdateRequest extends ConselhoCreateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueIgnoreId = Rule::unique('CONSELHO')->ignore($this->request->all()["CONSELHO_ID"],"CONSELHO_ID");
        return [
            "CONSELHO_ID" => ["required","integer"],
            'CONSELHO_TIPO' => ['required'],
            'CONSELHO_SIGLA' => ['required',$uniqueIgnoreId],
            'CONSELHO_NOME' => ['required',$uniqueIgnoreId]
        ];
    }
}
