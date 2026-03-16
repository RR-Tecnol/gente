<?php

namespace App\Http\Controllers;

use App\Http\Requests\Bairro\BairroCreateRequest;
use App\Http\Requests\Bairro\BairroUpdateRequest;
use App\Models\Bairro;
use App\Models\Uf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BairroController extends Controller
{
    public function view(Request $request)
    {
        $ufs = Uf::listar($request)->get();
        return view('bairro.bairro_view', compact('ufs'));
    }

    public function inserir(BairroCreateRequest $request)
    {
        $bairro = new Bairro($request->input());
        $bairro->save();

        return response([
            "cod" => 1,
            "msg" => "Bairro adicionado com sucesso",
            "retorno" => $bairro
        ], 200);
    }

    public function listar(Request $request)
    {
        $bairro = Bairro::listar($request)->paginate();

        return response([
            "cod" => 1,
            "msg" => "Bairro listado com sucesso",
            "retorno" => $bairro
        ], 200);
    }

    public function buscar(Request $request)
    {
        $bairro = Bairro::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Bairro id {$request->id} buscado com sucesso",
            "retorno" => $bairro
        ], 200);
    }

    public function pesquisar(Request $request)
    {
        $valorPesquisa = $request->query("valorPesquisa");
        return response(Bairro::pesquisar($valorPesquisa));
    }

    public function search(Request $request)
    {
        return response(Bairro::pesquisar($request->input("valorPesquisa")));
    }

    public function deletar(Request $request)
    {
        $bairro = Bairro::buscar($request->id);
        $bairro->BAIRRO_USUARIO_EXCLUSAO = Auth::id();
        $bairro->save();
        $bairro->delete();

        return response([
            "cod" => 1,
            "msg" => "Bairro id {$request->id} deletado com sucesso",
            "retorno" => $bairro
        ], 200);
    }

    public function alterar(BairroUpdateRequest $request)
    {
        $bairro = Bairro::buscar($request->BAIRRO_ID);
        $bairro->fill($request->post());
        $bairro->update();

        return response([
            "cod" => 1,
            "msg" => "Bairro id {$request->BAIRRO_ID} alterado com sucesso",
            "retorno" => $bairro
        ], 200);
    }
}
