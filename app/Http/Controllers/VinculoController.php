<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vinculo\VinculoCreateRequest;
use App\Http\Requests\Vinculo\VinculoUpdateRequest;
use App\Models\Vinculo;
use Illuminate\Http\Request;

class VinculoController extends Controller
{
    private $label = 'Vínculo';

    public function view()
    {
        return view('vinculo.vinculo_view');
    }

    public function inserir(VinculoCreateRequest $request)
    {
        $vinculo = new Vinculo($request->input());
        $vinculo->save();

        return response([
            "cod" => 1,
            "msg" => "$this->label adicionado com sucesso",
            "retorno" => $vinculo
        ], 200);
    }

    public function listar(Request $request)
    {
        $vinculo = Vinculo::listar($request)->paginate();

        return response([
            'retorno' => $vinculo,
            'msg' => "$this->label listado com sucesso",
        ], 200);
    }

    public function buscar(Request $request)
    {
        $vinculo = Vinculo::buscar($request->id);

        return response([
            'retorno' => $vinculo,
            'msg' => "$this->label buscado com sucesso",
        ], 200);
    }

    public function alterar(VinculoUpdateRequest $request)
    {
        $vinculo = Vinculo::buscar($request->VINCULO_ID);
        $vinculo->fill($request->input());
        $vinculo->update();

        return response([
            'retorno' => $vinculo,
            'msg' => "$this->label alterado com sucesso",
        ], 200);
    }
}
