<?php

namespace App\Http\Controllers;

use App\Http\Requests\Unidade\UnidadeCreateRequest;
use App\Http\Requests\Unidade\UnidadeUpdateRequest;
use App\Models\Atribuicao;
use App\Models\Cargo;
use App\Models\Setor;
use App\Models\TabelaGenerica;
use App\Models\Unidade;
use Illuminate\Http\Request;

class UnidadeController extends Controller
{
    public function view()
    {
        $tipoUnidades = TabelaGenerica::tipo_unidade();
        $unidadePortes = TabelaGenerica::unidadePorte();
        $atribuicoes = Atribuicao::where('ATRIBUICAO_ATIVA', 1)->get();
        $setores = Setor::select('SETOR_NOME', 'SETOR_SIGLA')->distinct()->get();

        return view('unidade.unidade_view', compact("tipoUnidades", "unidadePortes", "atribuicoes", 'setores'));
    }

    public function search(Request $request)
    {
        return response(Unidade::search($request->input('valorPesquisa')));
    }

    public function create(UnidadeCreateRequest $request)
    {
        $unidade = new Unidade($request->input());
        $unidade->save();

        Setor::create([
            "UNIDADE_ID" => $unidade->UNIDADE_ID,
            "SETOR_NOME" => 'NÚCLEO',
            "SETOR_SIGLA" => 'NÚCLEO',
            "SETOR_ATIVO" => 1,
        ]);

        return response(Unidade::buscar($unidade->UNIDADE_ID), 200);
    }

    public function update(UnidadeUpdateRequest $request)
    {
        $unidade = Unidade::buscar($request->UNIDADE_ID);
        $unidade->fill($request->post());
        $unidade->update();

        return response(Unidade::buscar($unidade->UNIDADE_ID), 200);
    }

    public function listar(Request $request)
    {
        $unidade = Unidade::listar($request);

        return response([
            "cod" => 1,
            "msg" => "Unidade listado com sucesso",
            "retorno" => $unidade
        ], 200);
    }

    public function pesquisar(Request $request)
    {
        $unidade = Unidade::pesquisar($request);

        return response([
            "cod" => 1,
            "msg" => "Unidade pesquisado com sucesso",
            "retorno" => $unidade
        ], 200);
    }

    public function buscar(Request $request)
    {
        $unidade = Unidade::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Unidade id {$request->id} buscado com sucesso",
            "retorno" => $unidade
        ], 200);
    }

    public function detalhes()
    {
        $unidade = Unidade::detalhes();

        return response([
            "cod" => 1,
            "msg" => "Unidade detalahada com sucesso",
            "retorno" => $unidade
        ], 200);
    }

    public function perfil()
    {
        $tipoUnidades = TabelaGenerica::tipo_unidade();
        $setores = Setor::all();
        $cargos = Cargo::all();

        return view('unidade.unidade_perfil', compact("tipoUnidades", "setores", "cargos"));
    }
}
