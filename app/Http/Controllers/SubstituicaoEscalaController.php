<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubstituicaoEscala\SubstituicaoEscalaCreateRequest;
use App\Http\Requests\SubstituicaoEscala\SubstituicaoEscalaUpdateRequest;
use App\Models\SubstituicaoEscala;
use Illuminate\Http\Request;

class SubstituicaoEscalaController extends Controller
{
    public function view()
    {
        return view('substituicao_escala.substituicao_escala_view');
    }

    public function inserir(SubstituicaoEscalaCreateRequest $request)
    {
        $substituicaoEscala = new SubstituicaoEscala($request->input());

        $substituicaoEscala->SUBSTITUICAO_ESCALA_DATA = date('m/d/Y H:s');
        $substituicaoEscala->save();

        return response($substituicaoEscala, 200);
    }

    public function listar(Request $request)
    {
        $substituicaoEscala = SubstituicaoEscala::listar($request)->paginate();

        return response($substituicaoEscala, 200);
    }

    public function buscar(Request $request)
    {
        $substituicaoEscala = SubstituicaoEscala::buscar($request->id);
        $substituicaoEscala->funcionario;

        return response([
            "cod" => 1,
            "msg" => "Substituicao Escala id {$request->id} buscado com sucesso",
            "retorno" => $substituicaoEscala
        ], 200);
    }

    public function deletar(Request $request)
    {
        $substituicaoEscala = SubstituicaoEscala::buscar($request->id);
        $substituicaoEscala->delete();

        return response([
            "cod" => 1,
            "msg" => "Substituicao Escala id {$request->id} deletado com sucesso",
            "retorno" => $substituicaoEscala
        ], 200);
    }

    public function alterar(SubstituicaoEscalaUpdateRequest $request)
    {
        $substituicaoEscala = SubstituicaoEscala::buscar($request->SUBSTITUICAO_ESCALA_ID);
        $substituicaoEscala->fill($request->post());
        $substituicaoEscala->update();

        return response([
            "cod" => 1,
            "msg" => "Substituicao Escala id {$request->SUBSTITUICAO_ESCALA_ID} alterado com sucesso",
            "retorno" => $substituicaoEscala
        ], 200);
    }
}
