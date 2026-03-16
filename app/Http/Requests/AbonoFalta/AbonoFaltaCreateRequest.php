<?php

namespace App\Http\Requests\AbonoFalta;

use App\Rules\ChecarAcessoUsuarioSetor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AbonoFaltaCreateRequest extends FormRequest
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $request =  $this->request->all();
        return [
            "DETALHE_ESCALA_ITEM_ID" => ["required","integer"],
            "ABONO_FALTA_JUSTIFICATIVA"=> ['required'],
            'detalheEscalaItem.detalheEscala.escala.SETOR_ID' => ['required',new ChecarAcessoUsuarioSetor],
        ];
    }

    public function attributes()
    {
        return [
            "DETALHE_ESCALA_ITEM_ID" => '<b>DATA DA ESCALA</b>',
            "ABONO_FALTA_JUSTIFICATIVA" => '<b>JUSTIFICATIVA</b>',
        ];
    }

    public function messages()
    {
        return [
//            "DETALHE_ESCALA_ITEM_ID.unique" => "Já possui uma :attribute cadastrado com nesse dia.",
            "DETALHE_ESCALA_ITEM_ID.required" => "Selecione uma <b>DATA DA ESCALA</b>."
        ];
    }
}
