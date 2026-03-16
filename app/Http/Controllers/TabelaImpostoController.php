<?php

namespace App\Http\Controllers;

use App\Http\Requests\TabelaImposto\TabelaImpostoCreateRequest;
use App\Http\Requests\TabelaImposto\TabelaImpostoDeleteRequest;
use App\Http\Requests\TabelaImposto\TabelaImpostoUpdateRequest;
use App\Models\Evento;
use App\Models\TabelaImposto;
use App\Models\VigenciaImposto;
use Illuminate\Http\Request;

class TabelaImpostoController extends Controller
{
    public function inserir(TabelaImpostoCreateRequest $request)
    {
        $tabelaimposto = new TabelaImposto($request->input());
        $tabelaimposto->save();
        $vigenciaImposto = VigenciaImposto::buscar($request->VIGENCIA_IMPOSTO_ID);
        $evento = Evento::buscar($vigenciaImposto->EVENTO_ID);
        return response(compact('evento', 'vigenciaImposto'), 200);
    }

    public function listar(Request $request)
    {
        $tabelaimposto = TabelaImposto::listar($request)->paginate();

        return response($tabelaimposto, 200);
    }

    public function buscar(Request $request)
    {
        $tabelaimposto = TabelaImposto::buscar($request->id);

        return response($tabelaimposto, 200);
    }

    public function alterar(TabelaImpostoUpdateRequest $request)
    {
        $tabelaimposto = TabelaImposto::buscar($request->TABELA_IMPOSTO_ID);
        $tabelaimposto->fill($request->post());
        $tabelaimposto->update();
        $vigenciaImposto = VigenciaImposto::buscar($request->VIGENCIA_IMPOSTO_ID);
        $evento = Evento::buscar($vigenciaImposto->EVENTO_ID);
        return response(compact('evento', 'vigenciaImposto'), 200);
    }

    public function deletar(TabelaImpostoDeleteRequest $request)
    {
        $tabelaimposto = TabelaImposto::buscar($request->TABELA_IMPOSTO_ID);
        $tabelaimposto->delete();

        $vigenciaImposto = VigenciaImposto::buscar($request->VIGENCIA_IMPOSTO_ID);
        $evento = Evento::buscar($vigenciaImposto->EVENTO_ID);
        return response(compact('evento', 'vigenciaImposto'), 200);
    }
}
