<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventoVinculo\EventoVinculoCreateRequest;
use App\Http\Requests\EventoVinculo\EventoVinculoDeleteRequest;
use App\Models\Evento;
use App\Models\EventoVinculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventoVinculoController extends Controller
{
    public function inserir(EventoVinculoCreateRequest $request)
    {
        DB::beginTransaction();
        foreach ($request->VINCULO_ID as $vinculoId) {
            $eventovinculo = new EventoVinculo($request->input());
            $eventovinculo->VINCULO_ID = $vinculoId;
            $eventovinculo->save();
        }
        DB::commit();

        $evento = Evento::buscar($request->EVENTO_ID);
        return response($evento, 200);
    }

    public function listar(Request $request)
    {
        $eventovinculo = EventoVinculo::listar($request)->paginate();

        return response($eventovinculo, 200);
    }

    public function buscar(Request $request)
    {
        $eventovinculo = EventoVinculo::buscar($request->id);

        return response($eventovinculo, 200);
    }

    public function deletar(EventoVinculoDeleteRequest $request)
    {
        $eventovinculo = EventoVinculo::buscar($request->EVENTO_VINCULO_ID);
        if ($eventovinculo->EVENTO_VINCULO_PROIBIDO == 1) {
            $eventovinculo->EVENTO_VINCULO_PROIBIDO = 0;
        } else {
            $eventovinculo->EVENTO_VINCULO_PROIBIDO = 1;
        }
        $eventovinculo->save();

        $evento = Evento::buscar($request->EVENTO_ID);
        return response($evento, 200);
    }
}
