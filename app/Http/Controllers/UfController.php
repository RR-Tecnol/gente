<?php

namespace App\Http\Controllers;

use App\Http\Requests\uf\UfRequest;
use App\Http\Requests\uf\UfUpdateRequest;
use App\Models\Uf;
use Illuminate\Http\Request;

class UfController extends Controller
{
    public function view()
    {
        return view('uf.uf_view');
    }

    public function inserir(UfRequest $request)
    {
        $uf = new Uf($request->input());
        $uf->save();

        return response([
            "cod" => 1,
            "msg" => "Uf adicionado com sucesso",
            "retorno" => $uf
        ], 200);
    }

    public function listar(Request $request)
    {
        $uf = Uf::listar($request)->paginate();

        return response([
            "cod" => 1,
            "msg" => "Uf listado com sucesso",
            "retorno" => $uf
        ], 200);
    }

    public function buscar(Request $request)
    {
        $uf = Uf::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Uf id {$request->id} buscado com sucesso",
            "retorno" => $uf
        ], 200);
    }

    public function deletar(Request $request)
    {
        return;
        $uf = Uf::buscar($request->id);
        $uf->UF_ATIVO = 0;
        $uf->save();

        return response([
            "cod" => 1,
            "msg" => "Uf id {$request->id} deletado com sucesso",
            "retorno" => $uf
        ], 200);
    }

    public function alterar(UfUpdateRequest $request)
    {
        $uf = Uf::buscar($request->UF_ID);
        $uf->fill($request->post());
        $uf->update();

        return response([
            "cod" => 1,
            "msg" => "Uf id {$request->UF_ID} alterado com sucesso",
            "retorno" => $uf
        ], 200);
    }
}
