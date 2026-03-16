<?php

namespace App\Http\Requests\DetalheEscalaItem;

use App\Rules\ChecarAcessoUsuarioSetor;
use App\Rules\DetalheEscalaItem\ChecarDataComPeriodoEscala;
use App\Rules\DiaTurnoUnico;
use App\Rules\Escala\ChecarEscalaDeferida;
use Illuminate\Foundation\Http\FormRequest;

class DetalheEscalaItemCreateRequest extends EscalaDetalheItemUpdateRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $request = $this->request->all();
        return [
            'ESCALA_ID' => ['required', new ChecarEscalaDeferida],
            'DETALHE_ESCALA_ITEM_DATA' => ['required', new ChecarDataComPeriodoEscala($request)],
            'TURNO_ID' => ['required'],
            'DETALHE_ESCALA_ID' => ['required', new DiaTurnoUnico($request)],
            "SETOR_ID" => [new ChecarAcessoUsuarioSetor],
        ];
    }
}
