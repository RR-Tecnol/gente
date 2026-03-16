<?php

namespace App\Http\Requests\DetalheEscalaItem;

use App\Rules\ChecarAcessoUsuarioSetor;
use App\Rules\Escala\ChecarFaltaAtrasoPeriodoCompAtual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class EscalaDetalheItemUpdateRequest extends FormRequest
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
//        dd($this->request->all());
        return [
            "DETALHE_ESCALA_ITEM_ID" => ["required","integer"],
            "DETALHE_ESCALA_ITEM_DATA" => ["required","date", new ChecarFaltaAtrasoPeriodoCompAtual],
//            "DETALHE_ESCALA_ITEM_OBSERVACAO" => ["required"],
            "DETALHE_ESCALA_ITEM_FALTA" =>(($this->request->all()['DETALHE_ESCALA_ITEM_FALTA']) == 1 && ($this->request->all()['DETALHE_ESCALA_ITEM_ATRASO']) == 1) ? 'different:DETALHE_ESCALA_ITEM_ATRASO' : '',
            "SETOR_ID" => [new ChecarAcessoUsuarioSetor],
        ];
    }

    public function attributes()
    {
        return[
            "DETALHE_ESCALA_ITEM_ID" => "<b>DETALHE ESCALA</b>",
            "DETALHE_ESCALA_ITEM_DATA" => "<b>DATA</b>",
            "DETALHE_ESCALA_ITEM_FALTA" => "<b>FALTA</b>",
            "DETALHE_ESCALA_ITEM_ATRASO" => "<b>ATRASO</b>",
//            "DETALHE_ESCALA_ITEM_OBSERVACAO" => "<b>OBSERVAÇÃO</b>",
            "ESCALA_ID" => "<b>ESCALA</b>",
            "TURNO_ID" => '<b>TURNO</b>',
        ];
    }

    public function messages()
    {
        return [
            "DETALHE_ESCALA_ITEM_FALTA.different" => "Você não pode colocar <b>FALTA</b> e <b>ATRASO</b> no mesmo dia."
        ];
    }

}
