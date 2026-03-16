<?php

namespace App\Http\Requests\DetalheEscalaItem;

use App\Rules\Escala\ChecarEscalaDeferida;
use Illuminate\Foundation\Http\FormRequest;

class DetalheEscalaItemDelete extends EscalaDetalheItemUpdateRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "DETALHE_ESCALA_ITEM_ID" => ['required'],
            "ESCALA_ID" => ['required',new ChecarEscalaDeferida],
        ];
    }
}
