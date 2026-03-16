<?php

namespace App\Http\Controllers;

use App\Http\Requests\Funcao\FuncaoCreateRequest;
use App\Http\Requests\Funcao\FuncaoUpdateRequest;
use App\Models\Funcao;
use Illuminate\Http\Request;

class FuncaoController extends Controller
{
    public function view()
    {
        return view('funcao.funcao_view');
    }

    public function inserir(FuncaoCreateRequest $request)
    {
        $funcao = new Funcao($request->input());
        $funcao->FUNCAO_ATIVA = 1;
        $funcao->save();

        return response([
            "cod" => 1,
            "msg" => "Função adicionada com sucesso",
            "retorno" => $funcao
        ], 200);
    }

    public function listar()
    {
        $funcao = Funcao::listar();

        return response([
            "cod" => 1,
            "msg" => "Função listada com sucesso",
            "retorno" => $funcao
        ], 200);
    }

    public function pesquisar(Request $request)
    {
        $funcao = Funcao::pesquisar($request);

        return response([
            "cod" => 1,
            "msg" => "Função pesquisada com sucesso",
            "retorno" => $funcao
        ], 200);
    }

    public function buscar(Request $request)
    {
        $funcao = Funcao::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Função id {$request->id} buscada com sucesso",
            "retorno" => $funcao
        ], 200);
    }

    public function deletar(Request $request)
    {
        $funcao = Funcao::buscar($request->id);
        $funcao->FUNCAO_ATIVA = 0;
        $funcao->save();

        return response([
            "cod" => 1,
            "msg" => "Função id {$request->id} deletada com sucesso",
            "retorno" => $funcao
        ], 200);
    }

    public function alterar(FuncaoUpdateRequest $request)
    {
        $funcao = Funcao::buscar($request->FUNCAO_ID);
        $funcao->fill($request->post());
        $funcao->update();

        return response([
            "cod" => 1,
            "msg" => "Função id {$request->FUNCAO_ID} alterada com sucesso",
            "retorno" => $funcao
        ], 200);
    }
}
