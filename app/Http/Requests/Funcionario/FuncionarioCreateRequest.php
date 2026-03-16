<?php

namespace App\Http\Requests\Funcionario;

use App\Rules\Funcionario\FuncionarioAtribuicaoLotacaoObrigatoriaRule;
use App\Rules\Funcionario\FuncionarioValidarAtribuicaoLotacaoFimRule;
use App\Rules\Funcionario\FuncionarioValidarAtribuicaoLotacaoInicioRule;
use App\Rules\Funcionario\FuncionarioValidarEntradaNascimentoRule;
use App\Rules\Funcionario\FuncionarioValidarFuncionarioDataFimLotacaoEncerrada;
use App\Rules\Funcionario\FuncionarioValidarLotacaoDataFinalRule;
use App\Rules\Funcionario\FuncionarioValidarLotacaoDataInicialRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FuncionarioCreateRequest extends FormRequest {
    public function authorize() {
        return Auth::check();
    }

    public function rules() {
        return [
            "FUNCIONARIO_DATA_INICIO"   => ["required", "date"],
            "FUNCIONARIO_TIPO_ENTRADA"  => ["required", "integer"],
            "PESSOA_ID"                 => [
                "bail",
                "required",
                "integer",
                Rule::unique("FUNCIONARIO")->where(function ($q) {
                    $q->where("PESSOA_ID", $this->input("PESSOA_ID"))
                        ->whereNull("FUNCIONARIO_DATA_FIM");
                }),
                new FuncionarioValidarEntradaNascimentoRule($this->input("FUNCIONARIO_DATA_INICIO"))
            ],
            "FUNCIONARIO_MATRICULA"     => [
//                "required",
                "max:10",
                Rule::unique("FUNCIONARIO")->where(function ($q) {
                    $q->where("FUNCIONARIO_MATRICULA", $this->post("FUNCIONARIO_MATRICULA"));
                })
            ],
            "FUNCIONARIO_DATA_FIM"      => ["nullable", "date", "after:FUNCIONARIO_DATA_INICIO", "required_with:FUNCIONARIO_TIPO_SAIDA"],
            "FUNCIONARIO_TIPO_SAIDA"    => ["required_with:FUNCIONARIO_DATA_FIM"],
            "lotacoes"                  => [
                "bail",
                "required",
                new FuncionarioAtribuicaoLotacaoObrigatoriaRule(),
                new FuncionarioValidarLotacaoDataInicialRule($this->input("FUNCIONARIO_DATA_INICIO")),
                new FuncionarioValidarLotacaoDataFinalRule($this->input("FUNCIONARIO_DATA_FIM")),
                new FuncionarioValidarAtribuicaoLotacaoInicioRule($this->input("FUNCIONARIO_DATA_INICIO")),
                new FuncionarioValidarAtribuicaoLotacaoFimRule($this->input("FUNCIONARIO_DATA_FIM")),
                new FuncionarioValidarFuncionarioDataFimLotacaoEncerrada($this->input("FUNCIONARIO_DATA_FIM"))
            ],
        ];
    }

    public function attributes() {
        return [
            "PESSOA_ID"                 => "<b>PESSOA</b>",
            "FUNCIONARIO_MATRICULA"     => "<b>MATRÍCULA</b>",
            "FUNCIONARIO_DATA_INICIO"   => "<b>DATA INICIAL</b>",
            "FUNCIONARIO_DATA_FIM"      => "<b>DATA FINAL</b>",
            "FUNCIONARIO_TIPO_ENTRADA"  => "<b>TIPO DE ENTRADA</b>",
            "FUNCIONARIO_TIPO_SAIDA"    => "<b>MOTIVO DA SAÍDA</b>",
            "lotacoes"                  => "<b>LOTAÇÃO</b>"
        ];
    }

    public function messages() {
        return [
            "lotacoes.required" => "Informe ao menos uma <b>:attribute</b> com atribuição",
            "PESSOA_ID.unique" => "O <b>FUNCIONÁRIO</b> já encontra-se cadastrado e ativo",
            "FUNCIONARIO_TIPO_SAIDA.required_with" => "O campo :attribute é obrigatório quando :values é informada",
            "FUNCIONARIO_DATA_FIM.required_with" => "O campo :attribute é obrigatório quando :values é informada"
        ];
    }
}
