<?php

namespace App\Http\Requests\SetorAtribuicao;

class SetorAtribuicaoUpdateRequest extends SetorAtribuicaoCreateRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "SETOR_ATRIBUICAO_ID" => ['required','integer'],
        ];
    }

}
