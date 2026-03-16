<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dossie\DossieCreateRequest;
use App\Http\Requests\Dossie\DossieUpdateRequest;
use App\Models\Dossie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DossieController extends Controller
{
    public function view()
    {
        return view('dossie.dossie_view');
    }

    public function inserir(DossieCreateRequest $request)
    {
        $dossie = new Dossie($request->input());
        $dossie->USUARIO_ID = Auth::id();
        $dossie->DOSSIE_DT_CADASTRO = date('Y-m-d H:i:s');

        $dossie->save();

        return response([
            "cod" => 1,
            "msg" => "Dossiê adicionado com sucesso",
            "retorno" => $dossie
        ], 200);
    }

    public function listar()
    {
        $dossie = Dossie::listar();

        return response([
            "cod" => 1,
            "msg" => "Dossiê listado com sucesso",
            "retorno" => $dossie
        ], 200);
    }

    public function pesquisar(Request $request)
    {
        $dossie = Dossie::pesquisar($request);

        return response([
            "cod" => 1,
            "msg" => "Dossiê pesquisado com sucesso",
            "retorno" => $dossie
        ], 200);
    }

    public function buscar(Request $request)
    {
        $dossie = Dossie::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Dossiê id {$request->id} buscado com sucesso",
            "retorno" => $dossie
        ], 200);
    }

    public function deletar(Request $request)
    {
        $dossie = Dossie::buscar($request->id);
        $dossie->delete();

        return response([
            "cod" => 1,
            "msg" => "Dossiê id {$request->id} deletado com sucesso",
            "retorno" => $dossie
        ], 200);
    }

    public function alterar(DossieUpdateRequest $request)
    {
        $dossie = Dossie::buscar($request->DOSSIE_ID);
        $dossie->fill($request->post());
        $dossie->update();

        return response([
            "cod" => 1,
            "msg" => "Dossiê id {$request->DOSSIE_ID} alterado com sucesso",
            "retorno" => $dossie
        ], 200);
    }
}
