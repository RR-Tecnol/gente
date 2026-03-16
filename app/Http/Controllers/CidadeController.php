<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cidade\CidadeCreateRequest;
use App\Http\Requests\Cidade\CidadeUpdateRequest;
use App\Models\Cidade;
use App\Models\Uf;
use Illuminate\Http\Request;

class CidadeController extends Controller
{
    public function view()
    {
        $ufs = Uf::all();
        return view('cidade.cidade_view', compact('ufs'));
    }

    public function inserir(CidadeCreateRequest $request)
    {
        $cidade = new Cidade($request->input());
        $cidade->save();

        return response([
            "cod" => 1,
            "msg" => "Cidade adicionado com sucesso",
            "retorno" => $cidade
        ], 200);
    }

    public function listar(Request $request)
    {
        $cidade = Cidade::listar($request)->paginate();

        return response([
            "cod" => 1,
            "msg" => "Cidade listado com sucesso",
            "retorno" => $cidade
        ], 200);
    }

    public function buscar(Request $request)
    {
        $cidade = Cidade::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Cidade id {$request->id} buscado com sucesso",
            "retorno" => $cidade
        ], 200);
    }

    public function pesquisar(Request $request)
    {
        return response(Cidade::pesquisar($request->query("valorPesquisa"), $request->query("ufId")));
    }

    public function search(Request $request)
    {
        return response(Cidade::pesquisar($request->input("valorPesquisa")));
    }

    public function deletar(Request $request)
    {
        return;
        $cidade = Cidade::buscar($request->id);
        $cidade->CIDADE_ATIVO = 0;
        $cidade->save();

        return response([
            "cod" => 1,
            "msg" => "Cidade id {$request->id} deletado com sucesso",
            "retorno" => $cidade
        ], 200);
    }

    public function alterar(CidadeUpdateRequest $request)
    {
        $cidade = Cidade::buscar($request->CIDADE_ID);
        $cidade->fill($request->post());
        $cidade->update();

        return response([
            "cod" => 1,
            "msg" => "Cidade id {$request->CIDADE_ID} alterado com sucesso",
            "retorno" => $cidade
        ], 200);
    }
}
