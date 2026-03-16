<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lotacao\LotacaoCreateRequest;
use App\Http\Requests\Lotacao\LotacaoSearchRequest;
use App\Http\Requests\Lotacao\LotacaoUpdateRequest;
use App\Models\Funcionario;
use App\Models\Lotacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LotacaoController extends Controller
{
    public function create(LotacaoCreateRequest $request)
    {
        $lotacao = new Lotacao($request->input());
        $lotacao->save();
        return response(Funcionario::buscar($request->input('FUNCIONARIO_ID')));
    }

    public function listar(Request $request)
    {
        $lotacao = Lotacao::listar($request);

        return response([
            "cod" => 1,
            "msg" => "Lotacao listado com sucesso",
            "retorno" => $lotacao
        ], 200);
    }

    public function pesquisar(LotacaoSearchRequest $request)
    {
        $lotacao = Lotacao::pesquisar($request);

        return response($lotacao, 200);
    }

    public function buscar(Request $request)
    {
        $lotacao = Lotacao::buscar($request->id);
        $lotacao->setor;
        $dateI = date_create($lotacao->LOTACAO_DATA_INICIO);
        if ($lotacao->LOTACAO_DATA_FIM) {
            $dateF = date_create($lotacao->LOTACAO_DATA_FIM);
            $lotacao->LOTACAO_DATA_FIM = date_format($dateF, "Y-m-d");
        }
        $lotacao->LOTACAO_DATA_INICIO = date_format($dateI, "Y-m-d");

        return response([
            "cod" => 1,
            "msg" => "Lotacao id {$request->id} buscado com sucesso",
            "retorno" => $lotacao
        ], 200);
    }

    public function deletar(Request $request)
    {
        $lotacao = Lotacao::with('atribuicaoLotacoes')->find($request->lotacaoId);

        if (!$lotacao) {
            return response()->json([
                "cod" => 0,
                "msg" => "Lotação não encontrada"
            ], 404);
        }

        $lotacao->atribuicaoLotacoes()->delete();

        $lotacao->delete();

        return response()->json([
            "cod" => 1,
            "msg" => "Lotação id {$request->id} deletada com sucesso",
            "retorno" => $lotacao
        ], 200);
    }

    public function alterar(LotacaoUpdateRequest $request)
    {
        $lotacao = Lotacao::buscar($request->LOTACAO_ID);
        $lotacao->fill($request->post());
        $lotacao->USUARIO_ID = Auth::id();
        $lotacao->update();

        return response([
            "cod" => 1,
            "msg" => "Lotacao id {$request->LOTACAO_ID} alterado com sucesso",
            "retorno" => $lotacao
        ], 200);
    }

    public function gestor()
    {
        $lotacao = Lotacao::gestao();

        return response([
            "cod" => 1,
            "msg" => "Lista de Gestores.",
            "retorno" => $lotacao
        ], 200);
    }

    public function getBySetor($setorId)
    {
        return response(Lotacao::getBySetor($setorId));
    }
}
