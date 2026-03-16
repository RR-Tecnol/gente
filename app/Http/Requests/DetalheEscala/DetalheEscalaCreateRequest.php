<?php

namespace App\Http\Requests\DetalheEscala;

use App\Rules\ChecarAcessoUsuarioSetor;
use App\Rules\DetalheEscala\EscalaComFuncionarioExistente;
use App\Rules\DetalheEscala\HorariosConflitantes;
use App\Rules\Escala\ChecarEscalaDeferida;
use App\Rules\Escala\NaoEditarEscalaPassadaRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class DetalheEscalaCreateRequest extends FormRequest
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
        return [
            "ESCALA_ID" => ["required","integer",new NaoEditarEscalaPassadaRule, new ChecarEscalaDeferida],
            "FUNCIONARIO_ID" => [
                "required","integer", 
                // new EscalaComFuncionarioExistente($this->request->all()['ESCALA_ID'])
            ],
            "ATRIBUICAO_ID" => ["required","integer"],
            "detalheEscalaItens" => ["required","array",new HorariosConflitantes($this->input())],

            "SETOR_ID" => [new ChecarAcessoUsuarioSetor]
        ];
    }

    public function attributes()
    {
        return[
            "ESCALA_ID" => "<b>ESCALA</b>",
            "FUNCIONARIO_ID" => "<b>FUNCIONARIO</b>",
            "ATRIBUICAO_ID" => "<b>ATRIBUICAO</b>",
            "TURNO_ID" => "<b>TURNO</b>",
            "detalheEscalaItens" => "<b>CALENDARIO</b>",
        ];
    }

    public function messages()
    {
        return [
            "DETALHE_ESCALA_FALTA.different" => "Você não pode colocar <b>FALTA</b> e <b>ATRASO</b> no mesmo dia."
        ];
    }
}
