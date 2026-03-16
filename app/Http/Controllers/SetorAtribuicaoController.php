<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetorAtribuicao\SetorAtribuicaoCreateRequest;
use App\Http\Requests\SetorAtribuicao\SetorAtribuicaoUpdateRequest;
use App\Models\Setor;
use App\Models\SetorAtribuicao;
use App\Models\Unidade;
use Illuminate\Http\Request;

class SetorAtribuicaoController extends Controller
{
    public function inserir(SetorAtribuicaoCreateRequest $request)
    {
        $setorAtribuicao = new SetorAtribuicao($request->input());
        $setorAtribuicao->save();

        $setor = Setor::find($request->SETOR_ID);
        $unidade = Unidade::buscar($setor->UNIDADE_ID);

        return response([
            "cod" => 1,
            "msg" => "Setor Atribuição adicionada com sucesso",
            "retorno" => $unidade
        ], 200);
    }

    public function listar(Request $request)
    {
        $setorAtribuicao = SetorAtribuicao::listar($request);

        return response([
            "cod" => 1,
            "msg" => "Setor Atribuição listada com sucesso",
            "retorno" => $setorAtribuicao
        ], 200);
    }

    public function alterar(SetorAtribuicaoUpdateRequest $request)
    {
        $setorAtribuicao = SetorAtribuicao::buscar($request->SETOR_ATRIBUICAO_ID);
        $setorAtribuicao->fill($request->post());
        $setorAtribuicao->update();

        $setor = Setor::find($request->SETOR_ID);
        $unidade = Unidade::buscar($setor->UNIDADE_ID);

        return response([
            "cod" => 1,
            "msg" => "Setor Atribuição id {$request->SETOR_ATRIBUICAO_ID} alterada com sucesso",
            "retorno" => $unidade
        ], 200);
    }

    public function deletar(Request $request)
    {
        $setorAtribuicao = SetorAtribuicao::buscar($request->SETOR_ATRIBUICAO_ID);
        $setorAtribuicao->delete();

        $setor = Setor::find($request->SETOR_ID);
        $unidade = Unidade::buscar($setor->UNIDADE_ID);

        return response([
            "cod" => 1,
            "msg" => "Setor Atribuição {$request->SETOR_ATRIBUICAO_ID} removido com sucesso",
            "retorno" => $unidade
        ], 200);
    }

    public function getBySetor($setorId)
    {
        return response(SetorAtribuicao::getBySetorNoPag($setorId));
    }
}
