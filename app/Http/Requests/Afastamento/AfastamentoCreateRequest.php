<?php

namespace App\Http\Requests\Afastamento;

use App\Rules\Afastamento\ChecarRepeticaoPeriodoAfastamento;
use App\Rules\ChecarAcessoUsuarioSetor;
use App\Rules\Ferias\ChecarRepeticaoPeriodoFerias;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AfastamentoCreateRequest extends FormRequest
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
            "FUNCIONARIO_ID" => ["required","integer"],
            "AFASTAMENTO_DATA_INICIO" => ["required", "before:AFASTAMENTO_DATA_FIM","date",'after_or_equal:'.date('Y-m-d'),
                                            new ChecarRepeticaoPeriodoAfastamento($this->request->all()['FUNCIONARIO_ID']),
                                            new ChecarRepeticaoPeriodoFerias($this->request->all()['FUNCIONARIO_ID'],null,'O servidor selecionado encontra-se em férias no período informado.')],
            "AFASTAMENTO_DATA_FIM" => ["required","date", new ChecarRepeticaoPeriodoAfastamento($this->request->all()['FUNCIONARIO_ID']),
                                        new ChecarRepeticaoPeriodoFerias($this->request->all()['FUNCIONARIO_ID'],null,'O servidor selecionado encontra-se em férias no período informado.')],
            "AFASTAMENTO_TIPO" => ["required"],
            'funcionario.lotacoes.*.SETOR_ID' => [new ChecarAcessoUsuarioSetor],
        ];
    }

    public function attributes()
    {
        return[
            "AFASTAMENTO_ID" => "<b>AFASTAMENTO ID</b>",
            "PESSOA_NOME" => "<b>PESSOA</b>",
            "FUNCIONARIO_ID" => "<b>FUNCIONARIO</b>",
            "AFASTAMENTO_DATA_INICIO" => "<b>DATA INÍCIO</b>",
            "AFASTAMENTO_DATA_FIM" => "<b>DATA FIM</b>",
            "AFASTAMENTO_TIPO" => "<b>TIPO DE AFASTAMENTO</b>",
        ];
    }

    public function messages()
    {
        return [
            "AFASTAMENTO_DATA_INICIO.after_or_equal" => "Não é permitido o lançamento de afastamento com datas retroativas.",
        ];
    }
}
