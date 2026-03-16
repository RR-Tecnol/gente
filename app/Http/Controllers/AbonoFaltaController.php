<?php

namespace App\Http\Controllers;

use App\Http\Requests\AbonoFalta\AbonoFaltaCreateRequest;
use App\Http\Requests\AbonoFalta\AbonoFaltaUpdateRequest;
use App\Models\AbonoFalta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AbonoFaltaController extends Controller
{
    private $label = 'Abono Falta';

    public function view()
    {
        return view('abono_falta.abono_falta_view');
    }

    public function inserir(AbonoFaltaCreateRequest $request)
    {
        $abonoFalta = AbonoFalta::buscar($request->DETALHE_ESCALA_ITEM_ID) ?:  new AbonoFalta();
        $abonoFalta->fill($request->input());
        $abonoFalta->USUARIO_ID = Auth::id();
        $abonoFalta->ABONO_FALTA_DATA = date('m/d/Y H:s');
        $abonoFalta->save();
        return response([
            "cod" => 1,
            "msg" => "$this->label adicionado com sucesso",
            "retorno" =>  AbonoFalta::buscar($request->DETALHE_ESCALA_ITEM_ID)
        ], 200);
    }

    public function listar(Request $request)
    {
        $abonoFalta = AbonoFalta::listar($request)->paginate();

        return response([
            "cod" => 1,
            "msg" => "$this->label listado com sucesso",
            "retorno" => $abonoFalta
        ], 200);
    }

    public function buscar(Request $request)
    {
        $abonoFalta = AbonoFalta::buscar($request->id);

        return response([
            "cod" => 1,
            "msg" => "$this->label buscado com sucesso",
            "retorno" => $abonoFalta
        ], 200);
    }

    public function alterar(AbonoFaltaUpdateRequest $request)
    {
        $abonoFalta = AbonoFalta::buscar($request->DETALHE_ESCALA_ITEM_ID);
        $abonoFalta->fill($request->post());
        $abonoFalta->update();

        return response([
            "cod" => 1,
            "msg" => "$this->label alterado com sucesso",
            "retorno" => $abonoFalta
        ], 200);
    }
}
