<?php

namespace App\Http\Requests\HistoricoEvento;

use App\Models\Evento;
use App\Rules\Evento\EventoTipoSalario;
use App\Rules\Evento\HistoricoEventoAtivoRule;
use App\Rules\Evento\ValidarSalarioMinimo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class HistoricoEventoCreateRequest extends FormRequest
{
    private $evento;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $this->evento = Evento::buscar($this->input('EVENTO_ID'));
        if($this->evento->EVENTO_SISTEMA == 1)
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
            "HISTORICO_EVENTO_INICIO" => ['required',new HistoricoEventoAtivoRule($this->evento)],
            "HISTORICO_EVENTO_FIM" => ['nullable','after_or_equal:HISTORICO_EVENTO_INICIO',new HistoricoEventoAtivoRule($this->evento)],
            "HISTORICO_EVENTO_CALCULO" => [
                'required',
                'not_in:6',
                // new EventoTipoSalario($this->evento),
                new ValidarSalarioMinimo
            ],
            "HISTORICO_EVENTO_VALOR" => [
                'required_if:HISTORICO_EVENTO_CALCULO,1,2,3,4',
                'prohibited_if:HISTORICO_EVENTO_CALCULO,5,6',
                'nullable',
                'numeric',
                'min:1',
                ($this->input('HISTORICO_EVENTO_CALCULO')!= 4) ?'max:100':'',
            ],
            "EVENTO_ID" => ['required'],
        ];
    }

    public function attributes()
    {
        return [
            "HISTORICO_EVENTO_ID" => '<b>ID</b>',
            "HISTORICO_EVENTO_INICIO" => '<b>INICIO</b>',
            "HISTORICO_EVENTO_FIM" => '<b>FIM</b>',
            "HISTORICO_EVENTO_CALCULO" => '<b>CALCULO</b>',
            "HISTORICO_EVENTO_VALOR" => '<b>VALOR</b>',
            "HISTORICO_EVENTO_EXCLUIDO" => '<b>EXCLUIDO</b>',
            "EVENTO_ID" => '<b>EVENTO</b>',
            "USUARIO_ID" => '<b>USUARIO</b>',
        ];
    }

    public function messages()
    {
        return [
            'HISTORICO_EVENTO_VALOR.required_if' => 'O campo :attribute é obrigatório.',
            'HISTORICO_EVENTO_CALCULO.not_in' => 'O campo :attribute : não pode criar o AUTOMÁTICO.',
            'HISTORICO_EVENTO_VALOR.prohibited_if' => 'O campo :attribute não deve ser informado para os tipos AUTOMÁTICO e VALOR MANUAL'
        ];
    }
}
