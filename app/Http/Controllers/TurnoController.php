<?php

namespace App\Http\Controllers;

use App\Http\Requests\Turno\TurnoCreateRequest;
use App\Http\Requests\Turno\TurnoUpdateRequest;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TurnoController extends Controller
{
    public function view()
    {
        return view('turno.turno_view');
    }

    public function inserir(TurnoCreateRequest $request)
    {
        $turno = new Turno($request->input());
        $turno->TURNO_ATIVO = 1;
        $turno->save();

        return response([
            "cod" => 1,
            "msg" => "Turno adicionado com sucesso",
            "retorno" => $turno
        ], 200);
    }

    public function listar()
    {
        $turno = Turno::listar();

        return response([
            "cod" => 1,
            "msg" => "Turno listado com sucesso",
            "retorno" => $turno
        ], 200);
    }

    public function pesquisar(Request $request)
    {
        $turno = Turno::pesquisar($request);

        return response([
            "cod" => 1,
            "msg" => "Turno pesquisado com sucesso",
            "retorno" => $turno
        ], 200);
    }

    public function search(Request $request)
    {
        return response(Turno::search($request));
    }

    public function buscar(Request $request)
    {
        $turno = Turno::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Turno id {$request->id} buscado com sucesso",
            "retorno" => $turno
        ], 200);
    }

    public function deletar(Request $request)
    {
        $turno = Turno::buscar($request->id);
        $turno->TURNO_USUARIO_EXCLUSAO = Auth::id();
        $turno->save(); // Save the user first
        $turno->delete(); // Then soft delete

        return response([
            "cod" => 1,
            "msg" => "Turno id {$request->id} deletado com sucesso",
            "retorno" => $turno
        ], 200);
    }

    public function alterar(TurnoUpdateRequest $request)
    {
        $turno = Turno::buscar($request->TURNO_ID);
        $turno->fill($request->post());
        $turno->update();

        return response([
            "cod" => 1,
            "msg" => "Turno id {$request->TURNO_ID} alterado com sucesso",
            "retorno" => $turno
        ], 200);
    }
}
