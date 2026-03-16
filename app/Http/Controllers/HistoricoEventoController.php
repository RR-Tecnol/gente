<?php

namespace App\Http\Controllers;

use App\Http\Requests\HistoricoEvento\HistoricoEventoCreateRequest;
use App\Http\Requests\HistoricoEvento\HistoricoEventoDeleteRequest;
use App\Http\Requests\HistoricoEvento\HistoricoEventoUpdateRequest;
use App\Models\Evento;
use App\Models\HistoricoEvento;
use Illuminate\Http\Request;

class HistoricoEventoController extends Controller
{
    public function inserir(HistoricoEventoCreateRequest $request)
    {
        $historicoevento = new HistoricoEvento($request->input());
        $historicoevento->HISTORICO_EVENTO_EXCLUIDO = 0;
        $historicoevento->save();
        $evento = Evento::buscar($request->EVENTO_ID);
        return response($evento, 200);
    }

    public function listar(Request $request)
    {
        $historicoevento = HistoricoEvento::listar($request)->paginate();

        return response($historicoevento, 200);
    }

    public function buscar(Request $request)
    {
        $historicoevento = HistoricoEvento::buscar($request->id);

        return response($historicoevento, 200);
    }

    public function alterar(HistoricoEventoUpdateRequest $request)
    {
        $historicoevento = HistoricoEvento::buscar($request->HISTORICO_EVENTO_ID);
        $historicoevento->fill($request->input());
        $historicoevento->update();

        $evento = Evento::buscar($request->EVENTO_ID);
        return response($evento, 200);
    }

    public function deletar(HistoricoEventoDeleteRequest $request)
    {
        $historicoevento = HistoricoEvento::buscar($request->HISTORICO_EVENTO_ID);
        if ($historicoevento->HISTORICO_EVENTO_EXCLUIDO) {
            $historicoevento->HISTORICO_EVENTO_EXCLUIDO = 0;
        } else {
            $historicoevento->HISTORICO_EVENTO_EXCLUIDO = 1;
        }
        $historicoevento->save();
        $evento = Evento::buscar($request->EVENTO_ID);
        return response($evento, 200);
    }
}
