<?php

namespace App\Http\Controllers;

use App\Http\Requests\Banco\BancoCreateRequest;
use App\Http\Requests\Banco\BancoUpdateRequest;
use App\Models\Banco;
use Illuminate\Http\Request;

class BancoController extends Controller
{
    private $label = 'Banco';

    public function view()
    {
        return view('banco.banco_view');
    }

    public function search(Request $request)
    {
        return response(Banco::search($request->input('valorPesquisa')));
    }

    public function inserir(BancoCreateRequest $request)
    {
        $banco = new Banco($request->input());
        $banco->save();

        return response([
            'retorno' => $banco,
            'msg' => "$this->label inserido com sucesso",
        ], 200);
    }

    public function listar(Request $request)
    {
        $banco = Banco::listar($request)->paginate();

        return response([
            'retorno' => $banco,
            'msg' => "$this->label listado com sucesso",
        ], 200);
    }

    public function buscar($id)
    {
        $banco = Banco::buscar($id);

        return response([
            'retorno' => $banco,
            'msg' => "$this->label buscado com sucesso",
        ], 200);
    }

    public function alterar(BancoUpdateRequest $request)
    {
        $banco = Banco::find($request->BANCO_ID);
        $banco->fill($request->input());
        $banco->update();

        return response([
            'retorno' => $banco,
            'msg' => "$this->label alterado com sucesso",
        ], 200);
    }
}
