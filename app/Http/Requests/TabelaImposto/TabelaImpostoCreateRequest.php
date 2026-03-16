<?php

namespace App\Http\Requests\TabelaImposto;

use App\Models\Evento;
use App\Models\VigenciaImposto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class TabelaImpostoCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $vigencia = VigenciaImposto::find($this->input('VIGENCIA_IMPOSTO_ID'));
        $evento = Evento::find($vigencia->EVENTO_ID);
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
            "TABELA_IMPOSTO_LIM_INFERIOR" => ['required'],
            "TABELA_IMPOSTO_LIM_SUPERIOR" => ['required'],
            "TABELA_IMPOSTO_PERCENTUAL" => ['required'],
            "TABELA_IMPOSTO_DEDUCAO" => ['required'],
            "VIGENCIA_IMPOSTO_ID" => ['required'], 
        ];
    }

    public function attributes()
    {
        return [
            "TABELA_IMPOSTO_ID" => "<b>ID</b>",
            "TABELA_IMPOSTO_VIGENCIA_INICIO" => "<b>VIGENCIA INICIO</b>",
            "TABELA_IMPOSTO_VIGENCIA_FIM" => "<b>VIGENCIA FIM</b>",
            "TABELA_IMPOSTO_LIM_INFERIOR" => "<b>LIMITE INFERIOR</b>",
            "TABELA_IMPOSTO_LIM_SUPERIOR" => "<b>LIMITE SUPERIOR</b>",
            "TABELA_IMPOSTO_PERCENTUAL" => "<b>PERCENTUAL</b>",
            "TABELA_IMPOSTO_DEDUCAO" => "<b>DEDUÇÃO</b>",
            "VIGENCIA_IMPOSTO_ID" => "<b>VIDENCIA IMPOSTO</b>",
        ];
    }
}
