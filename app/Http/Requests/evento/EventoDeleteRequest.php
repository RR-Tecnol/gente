<?php

namespace App\Http\Requests\evento;

use App\Models\Evento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class EventoDeleteRequest extends EventoCreateRequest
{
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
            'EVENTO_ID'=>['required'],
        ];
    }
}
