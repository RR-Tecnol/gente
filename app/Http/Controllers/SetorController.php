<?php

namespace App\Http\Controllers;

use App\Http\Requests\Setor\SetorCreateRequest;
use App\Http\Requests\Setor\SetorCreatesRequest;
use App\Http\Requests\Setor\SetorUpdateRequest;
use App\Models\Setor;
use App\Models\Unidade;
use Illuminate\Http\Request;

class SetorController extends Controller
{
    public function view()
    {
        return view('home');
    }

    public function creates(SetorCreatesRequest $request)
    {
        foreach ($request->setores as $setor) {
            $obj = new Setor($setor);
            $obj->SETOR_ATIVO = 1;
            $obj->UNIDADE_ID = $request->UNIDADE_ID;
            $obj->save();
        }

        return response(Unidade::buscar($request->UNIDADE_ID), 200);
    }

    public function create(SetorCreateRequest $request)
    {
        $setor = new Setor($request->input());
        $setor->SETOR_ATIVO = 1;
        $setor->save();

        return response(Unidade::buscar($request->UNIDADE_ID), 200);
    }

    public function update(SetorUpdateRequest $request)
    {
        $setor = Setor::buscar($request->SETOR_ID);
        $setor->fill($request->post());
        $setor->update();

        return response(Unidade::buscar($request->UNIDADE_ID), 200);
    }

    public function listar(Request $request)
    {
        $setor = Setor::listar($request);

        return response([
            "cod" => 1,
            "msg" => "Setor listado com sucesso",
            "retorno" => $setor
        ], 200);
    }

    public function pesquisar(Request $request)
    {
        $setor = Setor::pesquisar($request);

        return response([
            "cod" => 1,
            "msg" => "Setor pesquisado com sucesso",
            "retorno" => $setor
        ], 200);
    }

    public function buscar(Request $request)
    {
        $setor = Setor::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Setor id {$request->id} buscado com sucesso",
            "retorno" => $setor
        ], 200);
    }

    public function deletar(Request $request)
    {
        $setor = Setor::find($request->id);
        $setor->SETOR_ATIVO = 0;
        $setor->save();

        return response(Unidade::buscar($request->UNIDADE_ID), 200);
    }

    public function getByUnidade($unidadeId)
    {
        return response(Setor::getByUnidade($unidadeId));
    }
}
