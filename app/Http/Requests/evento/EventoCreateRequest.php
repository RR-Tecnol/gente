<?php

namespace App\Http\Requests\evento;

use App\Rules\DiferenteRule;
use App\Rules\Evento\EventoSistema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class EventoCreateRequest extends FormRequest
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
        return [
            "EVENTO_DESCRICAO" => ['required'],
            "EVENTO_SALARIO" => ['required',new DiferenteRule($this->input('EVENTO_IMPOSTO'))],
            "EVENTO_IMPOSTO" => ['required',new DiferenteRule($this->input('EVENTO_SALARIO'))],
            "EVENTO_INCIDENCIA" => ['required'],
            "EVENTO_SISTEMA" => ['required','not_in:1'],
        ];
    }

    public function attributes()
    {
        return [
            "EVENTO_ID" => '<b>ID</b>',
            "EVENTO_DESCRICAO" => '<b>DESCRIÇÃO</b>',
            "EVENTO_SALARIO" => '<b>SALÁRIO</b>',
            "EVENTO_IMPOSTO" => '<b>IMPOSTO</b>',
            "EVENTO_INCIDENCIA" => '<b>INCIDENCIA</b>',
            "EVENTO_SISTEMA" => '<b>SISTEMA</b>',
            "EVENTO_ATIVO" => '<b>ATIVO</b>',
        ];
    }

    public function messages()
    {
        return [
            'EVENTO_SISTEMA.not_in' => 'Eventos do tipo :attribute não poderão ser cadastrados'
        ];
    }
}
