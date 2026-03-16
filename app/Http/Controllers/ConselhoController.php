<?php

namespace App\Http\Controllers;

use App\Http\Requests\Conselho\ConselhoCreateRequest;
use App\Http\Requests\Conselho\ConselhoUpdateRequest;
use App\Models\Conselho;
use App\Models\TabelaGenerica;
use Illuminate\Http\Request;

class ConselhoController extends Controller
{
    private $label = 'Conselho';

    public function view()
    {
        $tipo_conselho = TabelaGenerica::tipo_conselho();
        return view('conselho.conselho_view', compact('tipo_conselho'));
    }

    public function search(Request $request)
    {
        return response(Conselho::search($request->get("valorPesquisa")));
    }

    public function inserir(ConselhoCreateRequest $request)
    {
        $conselho = new Conselho($request->input());
        $conselho->save();

        return response([
            'retorno' => $conselho,
            'msg' => "$this->label inserido com sucesso",
        ], 200);
    }

    public function listar(Request $request)
    {
        $conselho = Conselho::listar($request)->paginate();

        return response([
            'retorno' => $conselho,
            'msg' => "$this->label listado com sucesso",
        ], 200);
    }

    public function buscar($id)
    {
        $conselho = Conselho::buscar($id);

        return response([
            'retorno' => $conselho,
            'msg' => "$this->label buscado com sucesso",
        ], 200);
    }

    public function alterar(ConselhoUpdateRequest $request)
    {
        $conselho = Conselho::find($request->CONSELHO_ID);
        $conselho->fill($request->input());
        $conselho->update();

        return response([
            'retorno' => $conselho,
            'msg' => "$this->label alterado com sucesso",
        ], 200);
    }
}
