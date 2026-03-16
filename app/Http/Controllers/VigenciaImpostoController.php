<?php

namespace App\Http\Controllers;

use App\Http\Requests\VigenciaImposto\VigenciaImpostoCreateRequest;
use App\Http\Requests\VigenciaImposto\VigenciaImpostoDeleteRequest;
use App\Http\Requests\VigenciaImposto\VigenciaImpostoUpdateRequest;
use App\Models\Evento;
use App\Models\VigenciaImposto;
use Illuminate\Http\Request;

class VigenciaImpostoController extends Controller
{
    public function inserir(VigenciaImpostoCreateRequest $request)
    {
        $vigenciaimposto = new VigenciaImposto($request->input());
        $vigenciaimposto->save();
        $evento = Evento::buscar($request->EVENTO_ID);
        return response($evento, 200);
    }

    public function listar(Request $request)
    {
        $vigenciaimposto = VigenciaImposto::listar($request)->paginate();

        return response($vigenciaimposto, 200);
    }

    public function buscar(Request $request)
    {
        $vigenciaimposto = VigenciaImposto::buscar($request->id);

        return response($vigenciaimposto, 200);
    }

    public function alterar(VigenciaImpostoUpdateRequest $request)
    {
        $vigenciaimposto = VigenciaImposto::buscar($request->VIGENCIA_IMPOSTO_ID);
        $vigenciaimposto->fill($request->input());
        $vigenciaimposto->update();

        $evento = Evento::buscar($request->EVENTO_ID);
        return response($evento, 200);
    }

    public function deletar(VigenciaImpostoDeleteRequest $request)
    {
        $vigenciaimposto = VigenciaImposto::buscar($request->VIGENCIA_IMPOSTO_ID);
        $vigenciaimposto->delete();

        $evento = Evento::buscar($request->EVENTO_ID);
        return response($evento, 200);
    }
}
