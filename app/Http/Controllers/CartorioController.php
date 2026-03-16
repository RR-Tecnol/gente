<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cartorio\CartorioCreateRequest;
use App\Http\Requests\Cartorio\CartorioUpdateRequest;
use App\Models\Cartorio;
use App\Models\Uf;
use Illuminate\Http\Request;

class CartorioController extends Controller
{
    public function view()
    {
        $ufs = Uf::all();
        return view('cartorio.cartorio_view', compact('ufs'));
    }

    public function inserir(CartorioCreateRequest $request)
    {
        $cartorio = new Cartorio($request->input());
        $cartorio->save();
        return response($cartorio, 200);
    }

    public function listar(Request $request)
    {
        $cartorio = Cartorio::listar($request)->paginate();

        return response($cartorio, 200);
    }

    public function buscar($id)
    {
        $cartorio = Cartorio::buscar($id);

        return response($cartorio, 200);
    }

    public function search(Request $request)
    {
        $cartorio = Cartorio::search($request->input("valorPesquisa"));
        return response($cartorio);
    }

    public function alterar(CartorioUpdateRequest $request)
    {
        $cartorio = Cartorio::find($request->CARTORIO_ID);
        $cartorio->fill($request->input());
        $cartorio->update();

        return response($cartorio, 200);
    }
}
