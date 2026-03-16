<?php

namespace App\Http\Requests\EventoVinculo;

use App\Models\Evento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class EventoVinculoCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $evento = Evento::find($this->input('EVENTO_ID'));
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
            'EVENTO_ID' => ['required'],
            'VINCULO_ID' => ['required'],
            'EVENTO_VINCULO_PROIBIDO' => ['required'],
            // 'VINCULO_ID.*' => [
            //     'unique:EVENTO_VINCULO,VINCULO_ID',
            // ]
        ];
    }

    public function attributes()
    {
        return [
            'EVENTO_VINCULO_ID' => '<b>ID</b>',
            'EVENTO_ID' => '<b>EVENTO</b>',
            'VINCULO_ID' => '<b>VINCULO</b>',
            'EVENTO_VINCULO_PROIBIDO' => '<b>PROIBIDO</b>',
            'VINCULO_ID.*' => '<b>VINCULO</b>',
        ];
    }
}
