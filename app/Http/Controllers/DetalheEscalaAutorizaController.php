<?php

namespace App\Http\Controllers;

use App\Http\Requests\DetalheEscalaAutoriza\DetalheEscalaAutorizaCreateRequest;
use App\Http\Requests\DetalheEscalaAutoriza\DetalheEscalaAutorizaRequest;
use App\Models\DetalheEscala;
use App\Models\DetalheEscalaAutoriza;
use App\Models\Escala;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DetalheEscalaAutorizaController extends Controller
{
    public function inserir(DetalheEscalaAutorizaRequest $request)
    {
        $detalheEscalaAutoriza = new DetalheEscalaAutoriza($request->input());
        $detalheEscalaAutoriza->USUARIO_ID = Auth::id();
        $detalheEscalaAutoriza->DETALHE_ESCALA_AUTORIZA_DATA = date('m/d/Y H:s');
        $detalheEscalaAutoriza->save();

        return response([
            "cod" => 1,
            "msg" => "Detalhe Escala Autoriza adicionado com sucesso",
            "retorno" => $detalheEscalaAutoriza
        ], 200);
    }

    public function create(DetalheEscalaAutorizaCreateRequest $request)
    {
        $detalheEscalaAutoriza = DetalheEscalaAutoriza::with([])
            ->where("DETALHE_ESCALA_ID", $request->post("DETALHE_ESCALA_ID"))
            ->first();
        if ($detalheEscalaAutoriza == null) {
            $detalheEscalaAutoriza = new DetalheEscalaAutoriza($request->post());
            $detalheEscalaAutoriza->USUARIO_ID = auth()->id();
            $detalheEscalaAutoriza->DETALHE_ESCALA_AUTORIZA_DATA = date("Y-m-d");
            $detalheEscalaAutoriza->save();
        } else {
            $detalheEscalaAutoriza->fill($request->only(['DETALHE_ESCALA_AUTORIZA_JUSTIFICATIVA']));
            $detalheEscalaAutoriza->update();
        }
        $detalheEscala = DetalheEscala::find($request->post("DETALHE_ESCALA_ID"));
        return response(Escala::buscar($detalheEscala->ESCALA_ID));
    }

    public function buscar(Request $request)
    {
        $detalheEscalaAutoriza = DetalheEscalaAutoriza::buscar($request->id);

        $detalheEscalaAutoriza->detalhe_escala->detalheEscalaAlertas = $detalheEscalaAutoriza->detalhe_escala->detalheEscalaAlertas()->whereHas('tipo_alerta', function (Builder $query) {
            $query->where('TIPO_ALERTA_VISIVEL', '=', 1);
        })->get();

        foreach ($detalheEscalaAutoriza->detalhe_escala->detalheEscalaAlertas as $alerta)
            $alerta->tipo_alerta;
        return response([
            "cod" => 1,
            "msg" => "Detalhe Escala Autoriza adicionado com sucesso",
            "retorno" => $detalheEscalaAutoriza
        ], 200);
    }

    public function alterar(DetalheEscalaAutorizaRequest $request)
    {
        $detalheEscalaAutoriza = DetalheEscalaAutoriza::buscar($request->DETALHE_ESCALA_ID);
        $detalheEscalaAutoriza->fill($request->post());
        $detalheEscalaAutoriza->USUARIO_ID = Auth::id();
        $detalheEscalaAutoriza->DETALHE_ESCALA_AUTORIZA_DATA = date('m/d/Y H:s');
        $detalheEscalaAutoriza->update();

        return response([
            "cod" => 1,
            "msg" => "Detalhe Escala id {$request->DETALHE_ESCALA_ID} alterado com sucesso",
            "retorno" => $detalheEscalaAutoriza
        ], 200);
    }
}
