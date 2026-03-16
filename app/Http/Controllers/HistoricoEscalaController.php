<?php

namespace App\Http\Controllers;

use App\Http\Requests\HistoricoEscala\HistoricoEscalaCreateRequest;
use App\Http\Requests\HistoricoEscala\HistoricoEscalaIndeferirRequest;
use App\Http\Requests\HistoricoEscala\HistoricoEscalaRequest;
use App\Models\Escala;
use App\Models\HistoricoEscala;
use App\Models\Setor;
use App\Models\Unidade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HistoricoEscalaController extends Controller
{
    public function view()
    {
        $unidades = Unidade::where("UNIDADE_ATIVA", "=", 1)->get();
        $setores = Setor::where('SETOR_ATIVO', '=', 1)->get();
        $escalas = Escala::where('ESCALA_ATIVO', '=', 1)->get();

        return view('historico_escala.historico_escala_view', compact('unidades', 'setores', 'escalas'));
    }

    public function listar()
    {
        $historicoEscala = HistoricoEscala::listar();

        return response([
            "cod" => 1,
            "msg" => "Histórico Escala listado com sucesso",
            "retorno" => $historicoEscala
        ], 200);
    }

    public function buscar(Request $request)
    {
        $historicoEscala = HistoricoEscala::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "Histórico Escala id {$request->id} buscado com sucesso",
            "retorno" => $historicoEscala
        ], 200);
    }

    public function inserir(HistoricoEscalaRequest $request)
    {
        $escala = Escala::buscar($request->ESCALA_ID);
        $statusEscalaId = $request->STATUS_ESCALA_ID;
        $obs = $request->HISTORICO_ESCALA_OBSERVACAO;
        HistoricoEscala::setHistoricoEscala($escala, $statusEscalaId, $obs);

        return response([
            "cod" => 1,
            "msg" => "Histórico Escala salvado com sucesso",
            "retorno" => $escala
        ], 200);
    }

    public function avaliar(HistoricoEscalaCreateRequest $request)
    {
        $escalaJson = $request->post("escala");
        DB::beginTransaction();
        $historicoEscala = new HistoricoEscala($request->post('historicoEscala'));
        $historicoEscala->USUARIO_ID = auth()->id();
        $historicoEscala->ESCALA_ID = $escalaJson['ESCALA_ID'];
        $historicoEscala->HISTORICO_ESCALA_DATA = date("Y-m-d H:i:s");
        $historicoEscala->save();
        DB::commit();
        return response(Escala::buscar($historicoEscala->ESCALA_ID));
    }

    public function deferir(HistoricoEscalaCreateRequest $request)
    {
        $escalaJson = $request->post("escala");
        DB::beginTransaction();
        $historicoEscala = new HistoricoEscala($request->post('historicoEscala'));
        $historicoEscala->USUARIO_ID = auth()->id();
        $historicoEscala->ESCALA_ID = $escalaJson['ESCALA_ID'];
        $historicoEscala->HISTORICO_ESCALA_DATA = date("Y-m-d H:i:s");
        $historicoEscala->save();
        DB::commit();
        return response(Escala::buscar($historicoEscala->ESCALA_ID));
    }

    public function indeferir(HistoricoEscalaIndeferirRequest $request)
    {
        $escalaJson = $request->post("escala");

        DB::beginTransaction();
        $historicoEscala = new HistoricoEscala($request->post('historicoEscala'));
        $historicoEscala->USUARIO_ID = auth()->id();
        $historicoEscala->ESCALA_ID = $escalaJson['ESCALA_ID'];
        $historicoEscala->HISTORICO_ESCALA_DATA = date("Y-m-d H:i:s");
        $historicoEscala->save();
        DB::commit();
        return response(Escala::buscar($historicoEscala->ESCALA_ID));
    }

    public function create(HistoricoEscalaCreateRequest $request)
    {
        $escalaJson = $request->post("escala");
        DB::beginTransaction();
        $historicoEscala = new HistoricoEscala($request->post('historicoEscala'));
        $historicoEscala->USUARIO_ID = auth()->id();
        $historicoEscala->ESCALA_ID = $escalaJson['ESCALA_ID'];
        $historicoEscala->HISTORICO_ESCALA_DATA = date("Y-m-d H:i:s");
        $historicoEscala->save();
        DB::commit();
        return response(Escala::buscar($historicoEscala->ESCALA_ID));
    }
}
