<?php

namespace App\Http\Controllers;

use App\Http\Requests\FimLotacao\FimLotacaoCreateRequest;
use App\Http\Requests\FimLotacao\FimLotacaoUpdateRequest;
use App\Models\FimLotacao;
use Illuminate\Http\Request;

class FimLotacaoController extends Controller
{
    public function view()
    {
        return view('fim_lotacao.fim_lotacao_view');
    }

    public function inserir(FimLotacaoCreateRequest $request)
    {
        $fimLotacao = new FimLotacao($request->input());
        $fimLotacao->FIM_LOTACAO_ATIVA = 1;
        $fimLotacao->save();

        return response([
            "cod" => 1,
            "msg" => "Fim da Lotação adicionado com sucesso",
            "retorno" => $fimLotacao
        ], 200);
    }

    public function listar()
    {
        $fimLotacao = FimLotacao::listar();

        return response([
            "cod" => 1,
            "msg" => "Fim da Lotação listado com sucesso",
            "retorno" => $fimLotacao
        ], 200);
    }

    public function pesquisar(Request $request)
    {
        $fimLotacao = FimLotacao::pesquisar($request);

        return response([
            "cod" => 1,
            "msg" => "Fim da Lotação pesquisado com sucesso",
            "retorno" => $fimLotacao
        ], 200);
    }

    public function buscar(Request $request)
    {
        $fimLotacao = FimLotacao::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Fim da Lotação id {$request->id} buscado com sucesso",
            "retorno" => $fimLotacao
        ], 200);
    }

    public function deletar(Request $request)
    {
        $fimLotacao = FimLotacao::buscar($request->id);
        $fimLotacao->FIM_LOTACAO_ATIVA = 0;
        $fimLotacao->save();

        return response([
            "cod" => 1,
            "msg" => "Fim da Lotação id {$request->id} deletado com sucesso",
            "retorno" => $fimLotacao
        ], 200);
    }

    public function alterar(FimLotacaoUpdateRequest $request)
    {
        $fimLotacao = FimLotacao::buscar($request->FIM_LOTACAO_ID);
        $fimLotacao->fill($request->post());
        $fimLotacao->update();

        return response([
            "cod" => 1,
            "msg" => "Fim da Lotação id {$request->FIM_LOTACAO_ID} alterado com sucesso",
            "retorno" => $fimLotacao
        ], 200);
    }
}
