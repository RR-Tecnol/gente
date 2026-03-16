<?php

namespace App\Http\Requests\Tributacao;

use App\Models\Evento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class TributacaoCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $evento = Evento::find($this->input('EVENTO_ID_PROVENTO'));
        if($evento->EVENTO_SISTEMA == 1)
            return abort(419,'Eventos do tipo SISTEMA não poderão ser cadastrados e nem alterados; apenas visualizados');
        else
            return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'EVENTO_ID_PROVENTO' => ['required'],
            'EVENTO_ID_IMPOSTO' => ['required'],
            'TRIBUTACAO_ATIVA' => ['required'],
            'VINCULO_ID' => ['required'],
            // 'EVENTO_ID_IMPOSTO.*' => [
            //     'unique:TRIBUTACAO,EVENTO_ID_IMPOSTO',
            // ],
            // 'VINCULO_ID.*' => [
            //     'unique:TRIBUTACAO,VINCULO_ID',
            // ]
        ];
    }

    public function attributes()
    {
        return [
            'TRIBUTACAO_ID' => '<b>ID</b>',
            'EVENTO_ID_PROVENTO' => '<b>PROVENTO</b>',
            'EVENTO_ID_IMPOSTO' => '<b>IMPOSTO</b>',
            'TRIBUTACAO_ATIVA' => '<b>ATIVA</b>',
            'VINCULO_ID' => '<b>VINCULO</b>',
            'EVENTO_ID_IMPOSTO.*' => '<b>IMPOSTO</b>',
            'VINCULO_ID.*' => '<b>VINCULO</b>',
        ];
    }
}
