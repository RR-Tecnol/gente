<?php

namespace App\Http\Controllers;

use App\Http\Requests\DetalheEscala\DetalheEscalaCreateRequest;
use App\Http\Requests\DetalheEscala\DetalheEscalaDeleteRequest;
use App\Http\Requests\DetalheEscala\DetalheEscalaUpdateRequest;
use App\Models\DetalheEscala;
use App\Models\DetalheEscalaAlerta;
use App\Models\DetalheEscalaItem;
use App\Models\Escala;
use App\Models\HistoricoEscala;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DetalheEscalaController extends Controller
{
    public function inserir(DetalheEscalaCreateRequest $request)
    {
        DB::beginTransaction();
        $detalheEscala = new DetalheEscala($request->input());
        $detalheEscala->save();
        $this->salvarItem($detalheEscala, $request);
        $alerta = new DetalheEscalaAlerta();
        $alerta->gerarAlerta($detalheEscala);

        HistoricoEscala::setHistoricoEscala($detalheEscala->escala, 2);

        DB::commit();

        return response(Escala::buscar($detalheEscala->ESCALA_ID), 200);
    }

    public function listar(Request $request)
    {
        $detalheEscala = DetalheEscala::listar($request);

        return response([
            "cod" => 1,
            "msg" => "Detalhe Escala listado com sucesso",
            "retorno" => $detalheEscala
        ], 200);
    }

    public function buscar(Request $request)
    {
        $detalheEscala = DetalheEscala::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Detalhe Escala id {$request->id} buscado com sucesso",
            "retorno" => $detalheEscala
        ], 200);
    }

    public function deletar(DetalheEscalaDeleteRequest $request)
    {
        DB::beginTransaction();
        try {
            $detalheEscala = DetalheEscala::find($request->DETALHE_ESCALA_ID);
            DetalheEscalaAlerta::where('DETALHE_ESCALA_ID', $detalheEscala->DETALHE_ESCALA_ID)->delete();
            $detalheEscala->delete();
            HistoricoEscala::setHistoricoEscala($detalheEscala->escala, 2);
            DB::commit();
            return response(Escala::buscar($detalheEscala->ESCALA_ID), 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response(['error' => $e->getMessage()], 500);
        }
    }

    public function alterar(DetalheEscalaUpdateRequest $request)
    {
        $detalheEscala = DetalheEscala::buscar($request->DETALHE_ESCALA_ID);
        $detalheEscala->fill($request->post());
        $detalheEscala->USUARIO_ID = Auth::id();
        $detalheEscala->update();

        $this->salvarItem($detalheEscala, $request);

        return response(Escala::buscar($detalheEscala->ESCALA_ID), 200);
    }

    private function salvarItem(DetalheEscala $detalheEscala, Request $request)
    {
        $dias = $request->detalheEscalaItens;
        foreach ($dias as $dia) {
            $detalheEscalaItem = new DetalheEscalaItem($request->input());
            $detalheEscalaItem->DETALHE_ESCALA_ID = $detalheEscala->DETALHE_ESCALA_ID;
            $detalheEscalaItem->DETALHE_ESCALA_ITEM_DATA = $dia['DETALHE_ESCALA_ITEM_DATA'];
            $detalheEscalaItem->TURNO_ID = $dia['TURNO_ID'];
            $detalheEscalaItem->DETALHE_ESCALA_ITEM_FALTA = 0;
            $detalheEscalaItem->DETALHE_ESCALA_ITEM_ATRASO = 0;

            $detalheEscalaItem->save();
        }
    }

    public function resetarAlerta(Request $request)
    {

        $detalheEscala = DetalheEscala::buscar($request['DETALHE_ESCALA_ID']);

        $alerta = new DetalheEscalaAlerta();
        $alerta->gerarAlerta($detalheEscala);
        // HistoricoEscala::setHistoricoEscala($detalheEscala->escala, StatusEscalaEnum::ATUALIZADA);

        DB::commit();

        return response([
            "cod" => 1,
            "msg" => "Alertas resetados com sucesso.",
            "retorno" => Escala::buscar($detalheEscala->ESCALA_ID),
        ], 200);
    }
}
