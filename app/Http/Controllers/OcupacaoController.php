<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ocupacao\OcupacaoCreateRequest;
use App\Http\Requests\Ocupacao\OcupacaoUpdateRequest;
use App\Models\Ocupacao;
use Illuminate\Http\Request;

class OcupacaoController extends Controller
{
    private $label = 'Ocupacao';

    public function view()
    {
        return view('ocupacao.ocupacao_view');
    }

    public function search(Request $request)
    {
        return response(Ocupacao::search($request->input('valorPesquisa')));
    }

    public function inserir(OcupacaoCreateRequest $request)
    {
        $ocupacao = new Ocupacao($request->input());
        $ocupacao->save();

        return response([
            'retorno' => $ocupacao,
            'msg' => "$this->label inserido com sucesso",
        ], 200);
    }

    public function listar(Request $request)
    {
        $ocupacao = Ocupacao::listar($request)->paginate();

        return response([
            'retorno' => $ocupacao,
            'msg' => "$this->label listado com sucesso",
        ], 200);
    }

    public function buscar(Request $request)
    {
        $ocupacao = Ocupacao::buscar($request->id);

        return response([
            'retorno' => $ocupacao,
            'msg' => "$this->label buscado com sucesso",
        ], 200);
    }

    public function alterar(OcupacaoUpdateRequest $request)
    {
        $ocupacao = Ocupacao::buscar($request->OCUPACAO_ID);
        $ocupacao->fill($request->input());
        $ocupacao->update();

        return response([
            'retorno' => $ocupacao,
            'msg' => "$this->label alterado com sucesso",
        ], 200);
    }
}
