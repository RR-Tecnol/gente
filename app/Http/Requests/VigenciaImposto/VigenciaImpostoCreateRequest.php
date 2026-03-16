<?php

namespace App\Http\Requests\VigenciaImposto;

use App\Models\Evento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class VigenciaImpostoCreateRequest extends FormRequest
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
            "VIGENCIA_IMPOSTO_INICIO" => ['required'],
            "VIGENCIA_IMPOSTO_FIM" => ['required'],
            "EVENTO_ID" => ['required'],
        ];
    }

    public function attributes()
    {
        return [
            "VIGENCIA_IMPOSTO_ID" => "<b>ID</b>",
            "VIGENCIA_IMPOSTO_INICIO" => "<b>INICIO</b>",
            "VIGENCIA_IMPOSTO_FIM" => "<b>FIM</b>",
            "EVENTO_ID" => "<b>EVENTO</b>",
        ];
    }
}
