<?php

namespace App\Http\Controllers;

use App\Http\Requests\Funcionario\FuncionarioCreateRequest;
use App\Http\Requests\Funcionario\FuncionarioDeleteRequest;
use App\Http\Requests\Funcionario\FuncionarioUpdateRequest;
use App\Models\Atribuicao;
use App\Models\AtribuicaoLotacao;
use App\Models\Funcionario;
use App\Models\Lotacao;
use App\Models\Pessoa;
use App\Models\Setor;
use App\Models\TabelaGenerica;
use App\Models\Vinculo;
use App\MyLibs\RTG;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FuncionarioController extends Controller
{
    public function view()
    {
        return view('funcionario.funcionario_view')
            ->with([
                'tiposEntradaFuncionario' => TabelaGenerica::listarColunasTabela(RTG::TIPO_ENTRADA_FUNCIONARIO, 1),
                'tiposSaidaFuncionario' => TabelaGenerica::listarColunasTabela(RTG::TIPO_SAIDA_FUNCIONARIO, 1),
                'setores' => Setor::listAll(),
                'vinculos' => Vinculo::listAll(),
                'atribuicoes' => Atribuicao::listAll(),
                'cargasHorariasAtribuicao' => TabelaGenerica::listarColunasTabela(RTG::ATRIBUICAO_LOTACAO_CARGA_HORARIA, 1),
                'lotacaoTiposFim' => TabelaGenerica::listarColunasTabela(RTG::LOTACAO_TIPO_FIM, 1)
            ]);
    }

    public function create(FuncionarioCreateRequest $request)
    {
        try {
            DB::beginTransaction();
            $pessoa = Pessoa::find($request->PESSOA_ID);

            $funcionario = new Funcionario($request->post());
            if ($funcionario->FUNCIONARIO_MATRICULA == null)
                $funcionario->FUNCIONARIO_MATRICULA = str_pad($funcionario->PESSOA_ID, 6, 0, STR_PAD_LEFT) . "-" . $pessoa->funcionarios->count();
            $funcionario->save();

            $lotacoesJson = $request->post("lotacoes");
            if ($lotacoesJson) {
                foreach ($lotacoesJson as $lotacaoJson) {
                    $lotacao = new Lotacao($lotacaoJson);
                    $lotacao->FUNCIONARIO_ID = $funcionario->FUNCIONARIO_ID;
                    $lotacao->save();
                    $atribuicaoLotacoesJson = $lotacaoJson['atribuicaoLotacoes'];
                    if ($atribuicaoLotacoesJson) {
                        foreach ($atribuicaoLotacoesJson as $atribuicaoLotacaoJson) {
                            $atribuicaoLotacao = new AtribuicaoLotacao($atribuicaoLotacaoJson);
                            $atribuicaoLotacao->LOTACAO_ID = $lotacao->LOTACAO_ID;
                            $atribuicaoLotacao->save();
                        }
                    }
                }
            }

            Funcionario::setUsuario($funcionario->FUNCIONARIO_ID);
            Pessoa::atualizarStatus($funcionario->PESSOA_ID);
            DB::commit();
            return response([
                "pessoa" => Pessoa::getById($funcionario->PESSOA_ID),
                "funcionario" => Funcionario::buscar($funcionario->FUNCIONARIO_ID)
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(FuncionarioUpdateRequest $request)
    {
        try {
            DB::beginTransaction();
            $funcionario = Funcionario::find($request->input("FUNCIONARIO_ID"));
            $funcionario->fill($request->input());

            $funcionario->update();

            if ($funcionario->FUNCIONARIO_MATRICULA == null)
                $funcionario->FUNCIONARIO_MATRICULA = $funcionario->FUNCIONARIO_ID . date('Y');
            $funcionario->update();

            $lotacoesJson = $request->post("lotacoes");
            if ($lotacoesJson) {
                foreach ($lotacoesJson as $lotacaoJson) {
                    if ($lotacaoJson['LOTACAO_ID'] == null) {
                        $lotacao = new Lotacao($lotacaoJson);
                        $lotacao->FUNCIONARIO_ID = $funcionario->FUNCIONARIO_ID;
                        $lotacao->save();
                    } else {
                        $lotacao = Lotacao::find($lotacaoJson['LOTACAO_ID']);
                        $lotacao->fill($lotacaoJson);
                        $lotacao->FUNCIONARIO_ID = $funcionario->FUNCIONARIO_ID;
                        $lotacao->update();
                    }
                    $atribuicaoLotacoesJson = $lotacaoJson['atribuicaoLotacoes'];
                    if ($atribuicaoLotacoesJson) {
                        foreach ($atribuicaoLotacoesJson as $atribuicaoLotacaoJson) {
                            if ($atribuicaoLotacaoJson['ATRIBUICAO_LOTACAO_ID'] == null) {
                                $atribuicaoLotacao = new AtribuicaoLotacao($atribuicaoLotacaoJson);
                                $atribuicaoLotacao->LOTACAO_ID = $lotacao->LOTACAO_ID;
                                $atribuicaoLotacao->save();
                            } else {
                                $atribuicaoLotacao = AtribuicaoLotacao::find($atribuicaoLotacaoJson['ATRIBUICAO_LOTACAO_ID']);
                                $atribuicaoLotacao->fill($atribuicaoLotacaoJson);
                                $atribuicaoLotacao->LOTACAO_ID = $lotacao->LOTACAO_ID;
                                $atribuicaoLotacao->update();
                            }
                        }
                    }
                }
            }
            Pessoa::atualizarStatus($funcionario->PESSOA_ID);
            DB::commit();
            return response([
                "pessoa" => Pessoa::getById($funcionario->PESSOA_ID),
                "funcionario" => Funcionario::buscar($funcionario->FUNCIONARIO_ID)
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function search(Request $request)
    {
        return response(Funcionario::search($request->input("valorPesquisa")));
    }

    public function inserir(FuncionarioCreateRequest $request)
    {
        $funcionario = new Funcionario($request->input());
        $funcionario->save();

        return response([
            "cod" => 1,
            "msg" => "Funcionario adicionado com sucesso",
            "retorno" => $funcionario
        ], 200);
    }

    public function listar()
    {
        return response(Funcionario::listar());
    }

    public function pesquisar(Request $request)
    {
        $funcionario = Funcionario::pesquisar($request)->paginate(10);

        return response($funcionario, 200);
    }

    public function buscar(Request $request)
    {
        $funcionario = Funcionario::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Funcionario id {$request->id} buscado com sucesso",
            "retorno" => $funcionario
        ], 200);
    }

    public function deletar(FuncionarioDeleteRequest $request) {}

    public function alterar(FuncionarioUpdateRequest $request)
    {
        $funcionario = Funcionario::buscar($request->FUNCIONARIO_ID);
        $funcionario->fill($request->post());
        $funcionario->update();

        return response([
            "cod" => 1,
            "msg" => "Funcionario id {$request->FUNCIONARIO_ID} alterado com sucesso",
            "retorno" => $funcionario
        ], 200);
    }
}
