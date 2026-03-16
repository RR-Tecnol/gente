<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profissao\ProfissaoCreateRequest;
use App\Http\Requests\Profissao\ProfissaoUpdateRequest;
use App\Models\Profissao;
use App\Models\TabelaGenerica;
use Illuminate\Http\Request;

class ProfissaoController extends Controller
{
    public function view()
    {
        $escolaridades = TabelaGenerica::escolaridade();

        return view('profissao.profissao_view', compact('escolaridades'));
    }

    public function inserir(ProfissaoCreateRequest $request)
    {
        $profissao = new Profissao($request->input());
        $profissao->PROFISSAO_ATIVA = 1;
        $profissao->save();

        return response([
            "cod" => 1,
            "msg" => "Profissao adicionado com sucesso",
            "retorno" => $profissao
        ], 200);
    }

    public function listar()
    {
        $profissao = Profissao::listar();

        return response([
            "cod" => 1,
            "msg" => "Profissao listado com sucesso",
            "retorno" => $profissao
        ], 200);
    }

    public function pesquisar(Request $request)
    {
        $profissao = Profissao::pesquisar($request);

        return response([
            "cod" => 1,
            "msg" => "Profissao pesquisado com sucesso",
            "retorno" => $profissao
        ], 200);
    }

    public function buscar(Request $request)
    {
        $profissao = Profissao::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Profissao id {$request->id} buscado com sucesso",
            "retorno" => $profissao
        ], 200);
    }

    public function deletar(Request $request)
    {
        $profissao = Profissao::buscar($request->id);
        $profissao->PROFISSAO_ATIVA = 0;
        $profissao->save();

        return response([
            "cod" => 1,
            "msg" => "Profissao id {$request->id} deletado com sucesso",
            "retorno" => $profissao
        ], 200);
    }

    public function alterar(ProfissaoUpdateRequest $request)
    {
        $profissao = Profissao::buscar($request->PROFISSAO_ID);
        $profissao->fill($request->post());
        $profissao->update();

        return response([
            "cod" => 1,
            "msg" => "Profissao id {$request->PROFISSAO_ID} alterado com sucesso",
            "retorno" => $profissao
        ], 200);
    }
}
