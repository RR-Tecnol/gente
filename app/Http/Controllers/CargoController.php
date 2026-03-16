<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cargo\CargoUpdateRequest;
use App\Models\Cargo;
use App\Http\Requests\Cargo\CargoCreateRequest;
use App\Models\TabelaGenerica;
use Illuminate\Http\Request;

class CargoController extends Controller
{
    public function view()
    {
        $escolaridades = TabelaGenerica::escolaridade();

        return view('cargo.cargo_view', compact('escolaridades'));
    }

    public function inserir(CargoCreateRequest $request)
    {
        $cargo = new Cargo($request->input());
        $cargo->CARGO_ATIVO = 1;
        $cargo->save();

        return response([
            "cod" => 1,
            "msg" => "Cargo adicionado com sucesso",
            "retorno" => $cargo
        ], 200);
    }

    public function listar()
    {
        $cargo = Cargo::listar();

        return response([
            "cod" => 1,
            "msg" => "Cargo listado com sucesso",
            "retorno" => $cargo
        ], 200);
    }

    public function pesquisar(Request $request)
    {
        $cargo = Cargo::pesquisar($request);

        return response([
            "cod" => 1,
            "msg" => "Cargo pesquisado com sucesso",
            "retorno" => $cargo
        ], 200);
    }

    public function buscar(Request $request)
    {
        $cargo = Cargo::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Cargo id {$request->id} buscado com sucesso",
            "retorno" => $cargo
        ], 200);
    }

    public function deletar(Request $request)
    {
        $cargo = Cargo::buscar($request->id);
        $cargo->CARGO_ATIVO = 0;
        $cargo->save();

        return response([
            "cod" => 1,
            "msg" => "Cargo id {$request->id} deletado com sucesso",
            "retorno" => $cargo
        ], 200);
    }

    public function alterar(CargoUpdateRequest $request)
    {
        $cargo = Cargo::buscar($request->CARGO_ID);
        $cargo->fill($request->post());
        $cargo->update();

        return response([
            "cod" => 1,
            "msg" => "Cargo id {$request->CARGO_ID} alterado com sucesso",
            "retorno" => $cargo
        ], 200);
    }
}
